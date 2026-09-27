<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Repository;

use DC\LoginAsUser\Entity\Session;
use DC\LoginAsUser\Finder\SessionFinder;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\Repository\AdminLogRepository;
use XF\Session\StorageInterface;

class SessionRepository extends Repository
{
	public const RECENT_TARGET_LIMIT = 10;

	/**
	 * How far back getRecentTargetsForActor() will look. A bound is what keeps the query an index
	 * range on (actor_user_id, start_date) instead of a scan of one actor's whole history, and
	 * "someone I switched to more than three months ago" is not a quick-switch candidate anyway.
	 */
	public const RECENT_TARGET_WINDOW = 7776000; // 90 days

	/**
	 * Rows with no expiry are still closed after this long. Without it, a staff member who closes
	 * the browser mid-session leaves a row that says "active" forever and an ACP page that cries
	 * wolf. Comfortably longer than XF's four-hour session lifetime.
	 */
	public const STALE_AFTER = 86400;

	public function findLogsForList(): SessionFinder
	{
		return $this->finder(SessionFinder::class)
			->with(['User', 'Target'])
			->setDefaultOrder('start_date', 'DESC');
	}

	public function findActiveSessions(): SessionFinder
	{
		return $this->findLogsForList()->onlyActive();
	}

	/**
	 * The open row for one staff member, if any. This is the enforcement point for
	 * one-impersonation-at-a-time: a two-column equality seek on actor_user_id_end_date, so it can
	 * sit on the start path without costing anything.
	 */
	public function findOpenSessionForActor(int $actorUserId): ?Session
	{
		if (!$actorUserId)
		{
			return null;
		}

		return $this->finder(SessionFinder::class)
			->forActor($actorUserId)
			->onlyActive()
			->order('start_date', 'DESC')
			->fetchOne();
	}

	/**
	 * Finds the open row that owns a given public session id. Used by orphan recovery, where the
	 * marker has been destroyed by XF and the stale cookie is the only remaining link back.
	 */
	public function findOpenSessionBySessionId(string $sessionId): ?Session
	{
		if ($sessionId === '')
		{
			return null;
		}

		return $this->finder(SessionFinder::class)
			->where('actor_session_id', $sessionId)
			->onlyActive()
			->fetchOne();
	}

	/**
	 * Distinct targets for one actor, newest first, for the quick-switch overlay.
	 *
	 * Grouped rather than "last N rows, deduplicated in PHP": switching to the same person four
	 * times in an afternoon is normal, and a raw row list would spend the whole overlay on one name.
	 *
	 * @return array<int, string> [user_id => denormalised username]
	 */
	public function getRecentTargetsForActor(int $actorUserId, int $limit = self::RECENT_TARGET_LIMIT): array
	{
		if (!$actorUserId)
		{
			return [];
		}

		$rows = $this->db()->fetchAll(
			'SELECT target_user_id, MAX(target_username) AS target_username, MAX(start_date) AS last_start
				FROM xf_dcLoginAsUser_session
				WHERE actor_user_id = ?
					AND start_date > ?
					AND target_user_id > 0
				GROUP BY target_user_id
				ORDER BY last_start DESC
				LIMIT ' . max(1, $limit),
			[$actorUserId, \XF::$time - self::RECENT_TARGET_WINDOW]
		);

		$targets = [];

		foreach ($rows AS $row)
		{
			$targets[(int) $row['target_user_id']] = $row['target_username'];
		}

		return $targets;
	}

	/**
	 * Actors present in the log, for the ACP filter select.
	 *
	 * A select rather than an autocomplete because this set is bounded by "staff who have ever used
	 * the feature" - a handful - and picking from a list beats typing a name you have to remember.
	 * The target filter is the opposite case and uses an autocomplete instead.
	 *
	 * INNER JOIN, so an actor whose account is gone drops out of the dropdown; their rows are still
	 * reachable through the date filter and still name them.
	 *
	 * @return array<int, string>
	 */
	public function getUsersInLog(): array
	{
		return $this->db()->fetchPairs("
			SELECT user.user_id, user.username
			FROM (
				SELECT DISTINCT actor_user_id FROM xf_dcLoginAsUser_session
			) AS log
			INNER JOIN xf_user AS user ON (log.actor_user_id = user.user_id)
			ORDER BY user.username
		");
	}

	/**
	 * Open a session.
	 *
	 * Supersession happens first and unconditionally: one staff member, one impersonation, board
	 * wide. If they left one open on another device it is closed and that device's session is
	 * destroyed, so there is never a second live row to reconcile against and never a device
	 * quietly browsing as someone the log has already closed.
	 *
	 * @param array<string, bool> $suppressed
	 */
	public function openSession(
		User $actor,
		User $target,
		string $reason,
		string $ip,
		array $suppressed
	): Session
	{
		$existing = $this->findOpenSessionForActor($actor->user_id);

		if ($existing)
		{
			$this->closeSession($existing, Session::END_SUPERSEDED, 0, true);
		}

		$maxDuration = (int) $this->options()->dcLoginAsUser_maxDuration;

		/** @var Session $session */
		$session = $this->em->create(Session::class);
		$session->actor_user_id = $actor->user_id;
		$session->actor_username = $actor->username;
		$session->target_user_id = $target->user_id;
		$session->target_username = $target->username;
		$session->reason = $reason;
		$session->ip_address = $ip;
		$session->start_date = \XF::$time;
		$session->expiry_date = $maxDuration > 0 ? \XF::$time + ($maxDuration * 60) : 0;
		$session->suppressed = $suppressed;
		$session->save();

		return $session;
	}

	/**
	 * Close a session.
	 *
	 * $killSession destroys the actor's public session rather than returning them to themselves. It
	 * is the right answer whenever the close was not their own decision - a force-end or a
	 * supersession - because there is no request of theirs in flight to swap back, and leaving the
	 * cookie alive would leave them browsing as the target with no open row describing it.
	 *
	 * Clearing actor_session_id is not bookkeeping. It is the reason this table can hold live
	 * session ids at all: only currently-open rows carry one, so the permanent history is inert.
	 */
	public function closeSession(
		Session $session,
		string $endType,
		int $endedByUserId = 0,
		bool $killSession = false
	): void
	{
		if (!$session->is_active)
		{
			return;
		}

		$actorSessionId = $session->actor_session_id;

		$session->end_date = \XF::$time;
		$session->end_type = $endType;
		$session->ended_by_user_id = $endedByUserId;
		$session->actor_session_id = '';
		$session->save();

		if ($killSession && $actorSessionId !== '')
		{
			$this->deletePublicSession($actorSessionId);
		}
	}

	/**
	 * Ending someone else's session from the ACP. Always kills the underlying session: the actor is
	 * not here to be handed back to themselves, and failing closed - they become a guest, or revert
	 * via their own remember cookie - is the only outcome that cannot leave them impersonating
	 * after an administrator decided they should not be.
	 */
	public function forceEndSession(Session $session, User $endedBy): void
	{
		$this->closeSession($session, Session::END_FORCED, $endedBy->user_id, true);
	}

	/**
	 * Backstop for sessions nobody came back from. The per-request check inside the impersonated
	 * session handles the common case; this catches the browser that was simply closed, which the
	 * per-request check by definition never sees.
	 */
	public function closeStaleSessions(): int
	{
		$sessions = $this->finder(SessionFinder::class)
			->onlyActive()
			->whereOr(
				[
					['expiry_date', '>', 0],
					['expiry_date', '<=', \XF::$time],
				],
				[
					['expiry_date', 0],
					['start_date', '<', \XF::$time - self::STALE_AFTER],
				]
			)
			->fetch();

		$closed = 0;

		foreach ($sessions AS $session)
		{
			$endType = $session->expiry_date > 0 ? Session::END_EXPIRED : Session::END_STALE;
			$this->closeSession($session, $endType, 0, true);
			$closed++;
		}

		return $closed;
	}

	/**
	 * Retention. Modelled on XF\Repository\ModeratorLogRepository::pruneModeratorLogs(), including
	 * the "0 means keep forever" reading of the option.
	 *
	 * The end_date > 0 guard is the one addition, and it matters: an open row is not history, it is
	 * a live session. Deleting it would strand the actor - the listener that ends their
	 * impersonation looks the row up by id and would find nothing - and would drop a real session
	 * off the ACP active list.
	 */
	public function pruneLogs(?int $cutOff = null): int
	{
		if ($cutOff === null)
		{
			$logLength = (int) $this->options()->dcLoginAsUser_logLength;

			if (!$logLength)
			{
				return 0;
			}

			$cutOff = \XF::$time - 86400 * $logLength;
		}

		return $this->db()->delete(
			'xf_dcLoginAsUser_session',
			'start_date < ? AND end_date > 0',
			$cutOff
		);
	}

	/**
	 * The native admin-log row.
	 *
	 * Written in addition to our own row, not instead of it: xf_admin_log is pruned on the
	 * adminLogLength config (60 days by default here) and its ACP page is gated behind
	 * assertSuperAdmin(), so it is the wrong place to keep the durable record - but it is the right
	 * place for the event to surface to anyone auditing administrator activity generally.
	 *
	 * logAdminRequest() takes the IP as a STRING and converts it itself, so this must not pass the
	 * packed form. request_url is required and the raw insert path fails silently on an empty one.
	 *
	 * @param array<string, mixed> $data
	 */
	public function logAdminAction(int $actorUserId, string $verb, array $data): ?int
	{
		if (!$actorUserId)
		{
			return null;
		}

		$id = $this->repository(AdminLogRepository::class)->logAdminRequest(
			$actorUserId,
			'dc-login-as-user/' . $verb,
			$data,
			$this->app()->request()->getIp(false) ?: ''
		);

		return $id === null ? null : (int) $id;
	}

	/**
	 * Through the storage service, never raw SQL against xf_session. On a board with
	 * cache.sessions enabled the container hands back a CacheStorage and there is no such table.
	 */
	protected function deletePublicSession(string $sessionId): void
	{
		/** @var StorageInterface $storage */
		$storage = $this->app()->container('session.public.storage');
		$storage->deleteSession($sessionId);
	}
}

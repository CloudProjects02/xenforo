<?php

namespace DBTech\Security\Repository;

use DBTech\Security\Entity\Session;
use DBTech\Security\Finder\SessionFinder;
use XF\Db\Exception;
use XF\Entity\User;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class SessionRepository extends Repository
{
	/**
	 * @param User|null $user
	 *
	 * @return Finder
	 */
	public function getSessionsForUser(?User $user = null): Finder
	{
		$user = $user ?: \XF::visitor();

		return \XF::app()->finder(SessionFinder::class)
			->where('user_id', $user->user_id)
			->setDefaultOrder('start_date')
		;
	}

	/**
	 * Invalidate sessions older than X days (default: 30)
	 *
	 * @param int $days
	 */
	public function prune(int $days = 30): void
	{
		$this->db()->delete(
			'xf_dbtech_security_session',
			'last_activity_date < ?',
			[\XF::$time - ($days * 86400)]
		)
		;
	}

	/**
	 * Get a session by session ID
	 *
	 * @param string $sessionId
	 *
	 * @return array|bool
	 */
	public function getSessionBySessionId(string $sessionId): bool|array
	{
		return $this->db()->fetchRow('
			SELECT *
			FROM xf_dbtech_security_session
			WHERE session_id = ?
		', [$sessionId]);
	}

	/**
	 * Creates a new session for the current user
	 *
	 * @param User $user
	 * @param bool $setCookie
	 *
	 * @return Session
	 * @throws Exception
	 */
	public function createSession(User $user, bool $setCookie = true): Session
	{
		$sessionData = [
			'session_id' => md5(\XF::generateRandomString(16, true)),
			'user_id' => $user->user_id,
			'start_date' => \XF::$time,
			'last_activity_date' => \XF::$time,
			'user_agent' => \XF::app()->request()->getUserAgent() ?: 'N/A',
		];

		$query = "-- XFDB=noForceAllWrite
			INSERT IGNORE INTO xf_dbtech_security_session (
				session_id,
				user_id,
				start_date,
				last_activity_date,
				user_agent
			) VALUES (?, ?, ?, ?, ?)
		";

		$this->db()->query($query, $sessionData);

		$session = \XF::app()->em()->instantiateEntity(Session::class, $sessionData);

		if ($setCookie)
		{
			$this->setSessionCookie($session->session_id);
		}

		return $session;
	}

	/**
	 * Update session last activity date
	 *
	 * @param Session $session
	 * @param User|null $user
	 *
	 * @throws Exception
	 */
	public function updateSessionLastActive(Session $session, ?User $user = null): void
	{
		$ua = \XF::app()->request()->getUserAgent() ?: 'N/A';

		if ($user)
		{
			$this->db()->query('
				-- XFDB=noForceAllWrite
				INSERT INTO xf_dbtech_security_session
					(session_id, user_id, last_activity_date, user_agent)
				VALUES
					(?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					user_id = VALUES(user_id),
					last_activity_date = VALUES(last_activity_date),
					user_agent = VALUES(user_agent)
			', [$session->session_id, $user->user_id, \XF::$time, $ua]);
		}
		else
		{
			$this->db()->query('
				-- XFDB=noForceAllWrite
				INSERT INTO xf_dbtech_security_session
					(session_id, last_activity_date, user_agent)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE
					last_activity_date = VALUES(last_activity_date),
					user_agent = VALUES(user_agent)
			', [$session->session_id, \XF::$time, $ua]);
		}

		$this->setSessionCookie($session->session_id);
	}

	/**
	 * Get a session cookie
	 *
	 * @return array|bool|string
	 */
	public function getSessionCookie(): bool|array|string
	{
		return \XF::app()->request()->getCookie('dbtechSecuritySession');
	}

	/**
	 * Set session cookie
	 *
	 * @param string $sessionId
	 * @param int $lifetimeDays
	 */
	public function setSessionCookie(string $sessionId, int $lifetimeDays = 30): void
	{
		\XF::app()->response()->setCookie('dbtechSecuritySession', $sessionId, $lifetimeDays * 86400);
	}

	/**
	 * Delete session cookie
	 */
	public function deleteSessionCookie(): void
	{
		\XF::app()->response()->setCookie('dbtechSecuritySession', false);
	}
}
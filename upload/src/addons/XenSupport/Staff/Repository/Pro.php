<?php

namespace XenSupport\Staff\Repository;

use XF\Mvc\Entity\Repository;
use XF\Entity\User;
use XenSupport\Staff\Entity\Contact;
use XenSupport\Staff\Entity\Profile;

class Pro extends Repository
{
	// ============== Office hours ==============

	/**
	 * Office hours for one user, ordered by day + start time.
	 *
	 * @return \XF\Mvc\Entity\ArrayCollection|\XenSupport\Staff\Entity\Hours[]
	 */
	public function getHoursForUser(int $userId)
	{
		return $this->finder('XenSupport\Staff:Hours')
			->where('user_id', $userId)
			->order([['day_of_week', 'ASC'], ['start_time', 'ASC']])
			->fetch();
	}

	/**
	 * Replaces all office-hour slots for a user in one go (delete + insert).
	 *
	 * @param array<int, array{day_of_week:int,start_time:int,end_time:int,note:string}> $slots
	 */
	public function replaceHoursForUser(int $userId, array $slots): void
	{
		$db = \XF::db();
		$db->delete('xf_xs_staff_hours', 'user_id = ?', [$userId]);
		foreach ($slots AS $s)
		{
			$start = (int) $s['start_time'];
			$end   = (int) $s['end_time'];
			if ($end <= $start) continue;
			$db->insert('xf_xs_staff_hours', [
				'user_id'     => $userId,
				'day_of_week' => max(0, min(6, (int) $s['day_of_week'])),
				'start_time'  => $start,
				'end_time'    => $end,
				'note'        => mb_substr((string) ($s['note'] ?? ''), 0, 100),
			]);
		}
	}

	/**
	 * Is the staff currently within one of their office-hour slots?
	 */
	public function isInOfficeHours(int $userId, string $timezone = 'UTC'): bool
	{
		try
		{
			$now = new \DateTime('now', new \DateTimeZone($timezone ?: 'UTC'));
		}
		catch (\Throwable $e)
		{
			$now = new \DateTime('now', new \DateTimeZone('UTC'));
		}

		$day = (int) $now->format('w');         // 0..6
		$minute = ((int) $now->format('H')) * 60 + (int) $now->format('i');

		return (bool) \XF::db()->fetchOne(
			"SELECT 1 FROM xf_xs_staff_hours
			 WHERE user_id = ? AND day_of_week = ?
			   AND start_time <= ? AND end_time > ?
			 LIMIT 1",
			[$userId, $day, $minute, $minute]
		);
	}

	// ============== Online status ==============

	/**
	 * Resolve the displayable status for a staff member:
	 *   - if `online_override` is set to anything other than 'auto', use that
	 *   - else derive from XF session presence (last_activity within 5 min)
	 *   - plus optional office-hours indicator
	 *
	 * @return string  one of 'online', 'busy', 'away', 'offline'
	 */
	public function resolveOnlineStatus(int $userId, Profile $profile = null): string
	{
		if ($profile && $profile->online_override !== 'auto')
		{
			return $profile->online_override;
		}

		// Auto: based on last_activity
		$lastActivity = (int) \XF::db()->fetchOne(
			"SELECT last_activity FROM xf_user WHERE user_id = ?",
			[$userId]
		);
		$diff = \XF::$time - $lastActivity;
		if ($diff < 300)  return 'online';
		if ($diff < 900)  return 'away';
		return 'offline';
	}

	// ============== Contact form ==============

	/**
	 * Persist a new contact submission with rate-limit + spam checks.
	 *
	 * @return array{success: bool, error?: \XF\Phrase, contact?: Contact}
	 */
	public function submitContact(User $toUser, string $subject, string $message, bool $anonymous, string $fromEmail = '', ?User $fromUser = null): array
	{
		$toProfile = $this->em->find('XenSupport\Staff:Profile', $toUser->user_id);
		if (!$toProfile || !$toProfile->allow_contact)
		{
			return ['success' => false, 'error' => \XF::phrase('xen_staff_contact_disabled')];
		}
		if ($anonymous && !$toProfile->allow_anonymous_contact)
		{
			return ['success' => false, 'error' => \XF::phrase('xen_staff_contact_anonymous_disabled')];
		}

		$subject = trim($subject);
		$message = trim($message);
		if ($subject === '' || $message === '')
		{
			return ['success' => false, 'error' => \XF::phrase('xen_staff_contact_error_empty')];
		}

		// Rate limit (per IP). MD5 fits in the 32-char column.
		$ipHash = md5(\XF::app()->request()->getIp() ?: 'unknown');
		$since = \XF::$time - 3600;
		$recent = (int) \XF::db()->fetchOne(
			"SELECT COUNT(*) FROM xf_xs_staff_contact WHERE ip_hash = ? AND created_date >= ?",
			[$ipHash, $since]
		);
		$limit = (int) (\XF::options()->xenStaffContactHourlyLimit ?? 5);
		if ($recent >= $limit)
		{
			return ['success' => false, 'error' => \XF::phrase('xen_staff_contact_error_rate_limit')];
		}

		/** @var Contact $c */
		$c = $this->em->create('XenSupport\Staff:Contact');
		$c->to_user_id   = $toUser->user_id;
		$c->subject      = mb_substr($subject, 0, 150);
		$c->message      = mb_substr($message, 0, 5000);
		$c->is_anonymous = $anonymous;
		$c->from_user_id = ($anonymous || !$fromUser) ? 0 : $fromUser->user_id;
		$c->from_email   = (!$anonymous && $fromEmail) ? mb_substr($fromEmail, 0, 120) : '';
		$c->ip_hash      = $ipHash;
		$c->save();

		// Bump received counter on recipient profile
		\XF::db()->query(
			"UPDATE xf_xs_staff_profile SET contact_received_count = contact_received_count + 1 WHERE user_id = ?",
			[$toUser->user_id]
		);

		// Send an alert to the recipient
		try
		{
			/** @var \XF\Repository\UserAlert $alertRepo */
			$alertRepo = $this->repository('XF:UserAlert');
			$senderName = $anonymous ? 'Anonymous' : ($fromUser ? $fromUser->username : 'Guest');
			$alertRepo->alert(
				$toUser,
				$anonymous ? 0 : ($fromUser ? $fromUser->user_id : 0),
				$senderName,
				'user', $toUser->user_id,
				'xs_staff_contact',
				['subject' => $c->subject, 'contact_id' => $c->contact_id]
			);
		}
		catch (\Throwable $e) { /* silent */ }

		return ['success' => true, 'contact' => $c];
	}

	/**
	 * Mark a contact as replied, recording response time.
	 */
	public function markReplied(Contact $contact, User $byUser): void
	{
		if ($contact->status === 'replied') return;

		$contact->status = 'replied';
		$contact->replied_date = \XF::$time;
		$contact->reply_seconds = max(1, \XF::$time - $contact->created_date);
		$contact->save();

		// Update recipient stats
		\XF::db()->query(
			"UPDATE xf_xs_staff_profile
			 SET total_responses = total_responses + 1,
				 total_response_seconds = total_response_seconds + ?
			 WHERE user_id = ?",
			[$contact->reply_seconds, $byUser->user_id]
		);
	}

	// ============== Stats ==============

	/**
	 * Daily thanks count over the last N days for a given user.
	 *
	 * @return array<string, int>   key = YYYY-MM-DD, value = count
	 */
	public function getDailyThanksFor(int $userId, int $days = 30): array
	{
		$since = strtotime("-{$days} days", \XF::$time);
		$rows = \XF::db()->fetchAll(
			"SELECT FROM_UNIXTIME(thank_date, '%Y-%m-%d') AS day, COUNT(*) AS n
			 FROM xf_xs_staff_thank
			 WHERE to_user_id = ? AND thank_date >= ?
			 GROUP BY day ORDER BY day ASC",
			[$userId, $since]
		);
		$out = [];
		// Pre-fill all days with 0 for chart smoothness
		for ($i = $days; $i >= 0; $i--)
		{
			$day = date('Y-m-d', strtotime("-{$i} days", \XF::$time));
			$out[$day] = 0;
		}
		foreach ($rows AS $r)
		{
			$out[$r['day']] = (int) $r['n'];
		}
		return $out;
	}

	// ============== Badges ==============

	/**
	 * All badges, ordered for the admin library.
	 */
	public function findBadges()
	{
		return $this->finder('XenSupport\Staff:Badge')
			->order([['display_order', 'ASC'], ['title', 'ASC']])
			->fetch();
	}

	/**
	 * Assign a badge to a user (updates the profile + the badge's assigned_count).
	 */
	public function assignBadge(int $userId, int $badgeId): void
	{
		// Ensure a profile row exists first. A staff member who never edited their
		// profile has no row yet, and a bare UPDATE would silently match nothing and
		// lose the assignment. get-or-create guarantees the row.
		/** @var \XenSupport\Staff\Repository\Staff $staffRepo */
		$staffRepo = $this->repository('XenSupport\Staff:Staff');
		$profile = $staffRepo->getOrCreateProfile($userId);

		$current = (int) $profile->badge_id;
		if ($current === $badgeId)
		{
			return;
		}

		$profile->badge_id = $badgeId;
		$profile->save();

		$db = \XF::db();
		if ($current)
		{
			$db->query("UPDATE xf_xs_staff_badge SET assigned_count = GREATEST(0, assigned_count - 1) WHERE badge_id = ?", [$current]);
		}
		if ($badgeId)
		{
			$db->query("UPDATE xf_xs_staff_badge SET assigned_count = assigned_count + 1 WHERE badge_id = ?", [$badgeId]);
		}
	}
}

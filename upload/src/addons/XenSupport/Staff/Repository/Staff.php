<?php

namespace XenSupport\Staff\Repository;

use XF\Mvc\Entity\Repository;

class Staff extends Repository
{
	/**
	 * Returns an array of staff users grouped by usergroup, in the order
	 * configured by the ACP sort mode.
	 *
	 * Group bucketing strategy (since 1.0.1):
	 *   Many forums set everyone's primary user_group_id to "Registered" and use
	 *   secondary groups (Admin, Mod, …) for role assignment. We therefore bucket
	 *   each candidate user by the FIRST configured staff group (from
	 *   xenStaffUsergroups option) that the user belongs to via either primary
	 *   or secondary groups. The order configured by the admin in that option
	 *   becomes the on-page display order.
	 *
	 * @return array { groups: array, total: int }
	 */
	public function findStaffMembers(?int $limit = null): array
	{
		$options = \XF::options();
		$configuredGroupIds = $this->getStaffGroupIds(); // order matters (admin's preference)
		$useIsStaff = !empty($options->xenStaffUseIsStaff);
		$sortMode = $options->xenStaffSortMode ?? 'group_priority';

		$db = \XF::db();

		// Resolve candidate user_ids
		$candidateIds = [];

		foreach ($configuredGroupIds AS $gid)
		{
			$rows = $db->fetchAllColumn(
				"SELECT user_id FROM xf_user WHERE user_group_id = ? OR FIND_IN_SET(?, secondary_group_ids)",
				[$gid, $gid]
			);
			foreach ($rows AS $uid) { $candidateIds[$uid] = $uid; }
		}

		if ($useIsStaff)
		{
			$staffIds = $db->fetchAllColumn("SELECT user_id FROM xf_user WHERE is_staff = 1");
			foreach ($staffIds AS $uid) { $candidateIds[$uid] = $uid; }
		}

		if (!$candidateIds)
		{
			return ['groups' => [], 'total' => 0];
		}

		$candidateIds = array_values(array_unique($candidateIds));

		// Load full users
		$finder = $this->em->getFinder('XF:User')
			->whereIds($candidateIds)
			->where('user_state', 'valid');

		switch ($sortMode)
		{
			case 'join_date':
				$finder->order('register_date', 'ASC');
				break;
			case 'username':
				$finder->order('username', 'ASC');
				break;
			case 'thanks':
				$finder->with('XSStaffProfile');
				$finder->order([['XSStaffProfile.thanks_count', 'DESC'], ['username', 'ASC']]);
				break;
			case 'group_priority':
			default:
				// Inside each bucket: sort by display_style_priority desc (e.g. Admins above Mods)
				$finder->with('XSStaffUserGroup');
				$finder->order([['XSStaffUserGroup.display_style_priority', 'DESC'], ['username', 'ASC']]);
				break;
		}

		if ($limit) { $finder->limit($limit); }

		$users = $finder->fetch();

		// Bulk-load profiles
		$profiles = $this->em->getFinder('XenSupport\Staff:Profile')
			->whereIds($users->keys())
			->fetch();

		// Pre-load the user_group entities for ALL configured staff group IDs (one query)
		$groupTitles = [];
		$groupPriority = [];
		if ($configuredGroupIds)
		{
			$ugRows = $db->fetchAll(
				"SELECT user_group_id, title, display_style_priority
				 FROM xf_user_group
				 WHERE user_group_id IN (" . $db->quote($configuredGroupIds) . ")"
			);
			foreach ($ugRows AS $row)
			{
				$groupTitles[(int) $row['user_group_id']] = $row['title'];
				$groupPriority[(int) $row['user_group_id']] = (int) $row['display_style_priority'];
			}
		}

		// Build group buckets
		$groups = [];
		$bucketOrder = []; // tracks insertion order matching admin's config order

		// Pre-seed buckets in admin's configured order so groups appear in that order
		// even if a group has no members yet (we strip empty ones at the end).
		foreach ($configuredGroupIds AS $idx => $gid)
		{
			$groups[$gid] = [
				'title' => $groupTitles[$gid] ?? '',
				'priority' => $groupPriority[$gid] ?? 0,
				'order' => $idx,
				'users' => [],
			];
		}

		// "Generic staff" bucket for is_staff users not matching any configured group
		$genericKey = 'staff';

		foreach ($users AS $user)
		{
			$bucketKey = $this->resolveBucketKey($user, $configuredGroupIds);

			if ($bucketKey === null)
			{
				// is_staff fallback only
				if (!isset($groups[$genericKey]))
				{
					$groups[$genericKey] = [
						'title' => (string) \XF::phrase('xen_staff_generic_bucket'),
						'priority' => 0,
						'order' => 9999,
						'users' => [],
					];
				}
				$bucketKey = $genericKey;
			}

			$groups[$bucketKey]['users'][] = [
				'user' => $user,
				'profile' => $profiles[$user->user_id] ?? null,
			];
		}

		// Strip empty buckets
		$groups = array_filter($groups, fn ($b) => !empty($b['users']));

		// Order: by config order ascending (i.e. admin's chosen order)
		uasort($groups, fn ($a, $b) => $a['order'] <=> $b['order']);

		return ['groups' => $groups, 'total' => $users->count()];
	}

	/**
	 * Returns the first configured staff group_id that $user is a member of
	 * (checking primary then secondary), preserving the admin's preference order.
	 * Returns null when the user matched only via is_staff fallback.
	 */
	protected function resolveBucketKey(\XF\Entity\User $user, array $configuredGroupIds): ?int
	{
		if (!$configuredGroupIds)
		{
			return null;
		}

		$userPrimary = (int) $user->user_group_id;

		// XF auto-decodes secondary_group_ids to an array via the entity getter,
		// but raw DB rows / older code paths can still hand us a string. Normalise.
		$rawSecondary = $user->secondary_group_ids;
		if (is_array($rawSecondary))
		{
			$userSecondary = array_map('intval', $rawSecondary);
		}
		else
		{
			$userSecondary = array_filter(array_map(
				'intval',
				explode(',', (string) $rawSecondary)
			));
		}

		foreach ($configuredGroupIds AS $gid)
		{
			$gid = (int) $gid;
			if ($userPrimary === $gid)
			{
				return $gid;
			}
			if (in_array($gid, $userSecondary, true))
			{
				return $gid;
			}
		}

		return null;
	}

	public function getStaffGroupIds(): array
	{
		$raw = \XF::options()->xenStaffUsergroups ?? '';
		if (is_array($raw))
		{
			return array_values(array_filter(array_map('intval', $raw)));
		}
		if (!$raw) return [];
		$parts = preg_split('/[,\s]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
		return array_values(array_filter(array_map('intval', $parts)));
	}

	public function isStaff(\XF\Entity\User $user): bool
	{
		if (!$user->user_id) return false;
		$options = \XF::options();
		if (!empty($options->xenStaffUseIsStaff) && $user->is_staff) return true;

		$groupIds = $this->getStaffGroupIds();
		if (!$groupIds) return false;
		if (in_array((int) $user->user_group_id, $groupIds, true)) return true;
		foreach (explode(',', (string) $user->secondary_group_ids) AS $sg)
		{
			if (in_array((int) $sg, $groupIds, true)) return true;
		}
		return false;
	}

	public function getOrCreateProfile(int $userId): \XenSupport\Staff\Entity\Profile
	{
		/** @var \XenSupport\Staff\Entity\Profile $profile */
		$profile = $this->em->find('XenSupport\Staff:Profile', $userId);
		if (!$profile)
		{
			$profile = $this->em->create('XenSupport\Staff:Profile');
			$profile->user_id = $userId;
		}
		return $profile;
	}

	public function hasThanked(int $fromUserId, int $toUserId): bool
	{
		return (bool) \XF::db()->fetchOne(
			"SELECT thank_id FROM xf_xs_staff_thank WHERE from_user_id = ? AND to_user_id = ?",
			[$fromUserId, $toUserId]
		);
	}

	public function recordThank(int $fromUserId, int $toUserId): bool
	{
		if ($fromUserId <= 0 || $toUserId <= 0 || $fromUserId === $toUserId) return false;
		if ($this->hasThanked($fromUserId, $toUserId)) return false;

		$db = \XF::db();
		$db->insert('xf_xs_staff_thank', [
			'from_user_id' => $fromUserId,
			'to_user_id'   => $toUserId,
			'thank_date'   => \XF::$time,
		]);
		// Atomic increment
		$profile = $this->getOrCreateProfile($toUserId);
		$profile->save();
		$db->query("UPDATE xf_xs_staff_profile SET thanks_count = thanks_count + 1 WHERE user_id = ?", [$toUserId]);
		return true;
	}

	public function getThanksCount(int $userId): int
	{
		return (int) \XF::db()->fetchOne(
			"SELECT thanks_count FROM xf_xs_staff_profile WHERE user_id = ?",
			[$userId]
		);
	}

	/**
	 * Live counters for the Pro hero header: how many staff are online right
	 * now, total thanks given, average response time to contact messages, and
	 * total active staff. Cached implicitly via XF's query caching; cheap to
	 * recompute on every staff page load.
	 *
	 * @param int[] $staffUserIds candidate staff user_ids (pass the same set
	 *                            that findStaffMembers used to keep counts
	 *                            consistent with the rendered list)
	 * @return array{online:int,total_thanks:int,avg_response_min:float,active:int}
	 */
	public function getLiveStats(array $staffUserIds = []): array
	{
		$db = \XF::db();
		$idsClause = '';
		if ($staffUserIds)
		{
			$ids = array_filter(array_map('intval', $staffUserIds));
			if ($ids)
			{
				$idsClause = ' AND user_id IN (' . implode(',', $ids) . ')';
			}
		}

		// Online: last_activity within 5 minutes (matches the green dot on the
		// staff cards). The current visitor is always counted when they belong to
		// the staff set: they are viewing this page right now, yet xf_user
		// .last_activity is only refreshed at the END of the request, so a staff
		// member who just opened the page would otherwise read as offline and the
		// counter would wrongly show 0.
		$onlineSince = \XF::$time - 300;
		$online = (int) $db->fetchOne(
			"SELECT COUNT(*) FROM xf_user WHERE last_activity >= ?" . $idsClause,
			[$onlineSince]
		);

		$visitorId = (int) \XF::visitor()->user_id;
		$staffIdsInt = array_filter(array_map('intval', $staffUserIds));
		if ($visitorId && in_array($visitorId, $staffIdsInt, true))
		{
			$alreadyCounted = (bool) $db->fetchOne(
				"SELECT 1 FROM xf_user WHERE user_id = ? AND last_activity >= ?",
				[$visitorId, $onlineSince]
			);
			if (!$alreadyCounted)
			{
				$online++;
			}
		}

		// Total thanks across all staff profiles (uses bucket of staff_user_ids if
		// provided, else everyone with a profile)
		$totalThanks = 0;
		if ($staffUserIds)
		{
			$ids = array_filter(array_map('intval', $staffUserIds));
			if ($ids)
			{
				$totalThanks = (int) $db->fetchOne(
					"SELECT SUM(thanks_count) FROM xf_xs_staff_profile
					 WHERE user_id IN (" . implode(',', $ids) . ")"
				);
			}
		}
		else
		{
			$totalThanks = (int) $db->fetchOne(
				"SELECT SUM(thanks_count) FROM xf_xs_staff_profile"
			);
		}

		// Avg response time to contact messages (Pro feature, may be empty)
		$avgSec = 0;
		try
		{
			$row = $db->fetchRow(
				"SELECT SUM(total_response_seconds) AS s, SUM(total_responses) AS n
				 FROM xf_xs_staff_profile WHERE total_responses > 0"
				. ($idsClause ? ' AND' . substr($idsClause, 4) : '')
			);
			if ($row && (int) $row['n'] > 0)
			{
				$avgSec = (int) ($row['s'] / $row['n']);
			}
		}
		catch (\Throwable $e) { /* Pro columns may not exist on Free upgrade */ }

		$avgMin = $avgSec > 0 ? round($avgSec / 60, 1) : 0.0;

		return [
			'online'           => $online,
			'total_thanks'     => $totalThanks,
			'avg_response_min' => $avgMin,
			'active'           => count($staffUserIds),
		];
	}

	/**
	 * Returns the single most-thanked staff member to feature in the
	 * "Pro hero" card at the top of the page.
	 *
	 * @return array{user:\XF\Entity\User, profile:?\XenSupport\Staff\Entity\Profile}|null
	 */
	public function getTopStaff(array $candidateIds = []): ?array
	{
		if (!$candidateIds) { return null; }

		$ids = array_filter(array_map('intval', $candidateIds));
		if (!$ids) { return null; }

		$topUserId = (int) \XF::db()->fetchOne(
			"SELECT p.user_id FROM xf_xs_staff_profile p
			 INNER JOIN xf_user u ON u.user_id = p.user_id
			 WHERE p.user_id IN (" . implode(',', $ids) . ") AND u.user_state = 'valid'
			 ORDER BY p.thanks_count DESC, p.last_edit_date DESC
			 LIMIT 1"
		);

		if (!$topUserId) { return null; }

		/** @var \XF\Entity\User|null $user */
		$user = $this->em->find('XF:User', $topUserId);
		if (!$user) { return null; }

		/** @var \XenSupport\Staff\Entity\Profile|null $profile */
		$profile = $this->em->find('XenSupport\Staff:Profile', $topUserId);

		return ['user' => $user, 'profile' => $profile];
	}
}

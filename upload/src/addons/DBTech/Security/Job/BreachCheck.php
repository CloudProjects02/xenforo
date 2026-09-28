<?php

namespace DBTech\Security\Job;

use DBTech\Security\Repository\WatcherRepository;
use XF\Entity\User;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class BreachCheck extends AbstractRebuildJob
{
	/**
	 * @param $start
	 * @param $batch
	 *
	 * @return array
	 */
	protected function getNextIds($start, $batch): array
	{
		$db = \XF::app()->db();

		return $db->fetchAllColumn($db->limit(
			'
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			',
			$batch
		), $start);
	}

	/**
	 * @param $id
	 */
	protected function rebuildById($id): void
	{
		$user = \XF::app()->em()->find(User::class, $id, ['Profile']);
		if (!$user)
		{
			return;
		}

		$repo = \XF::app()->repository(WatcherRepository::class);
		$repo->breachCheck($user);
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('users');
	}
}
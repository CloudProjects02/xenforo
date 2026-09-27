<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Trophy;
use XF\Entity\User;

/**
 * @extends \XF\Repository\TrophyRepository
 */
class TrophyRepository extends XFCP_TrophyRepository
{
	/**
	 * @param Trophy $trophy
	 * @param User $user
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function awardTrophyToUser(Trophy $trophy, User $user)
	{
		$previous = parent::awardTrophyToUser($trophy, $user);

		if ($previous)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			$eventTriggerRepo->getHandler('trophy')
				->apply($trophy->trophy_id, [
					'trophy_id' => $trophy->trophy_id,
					'timestamp' => \XF::$time,
					'content_type' => 'trophy',
					'content_id' => $trophy->trophy_id,
				], $user)
			;
		}

		return $previous;
	}
}
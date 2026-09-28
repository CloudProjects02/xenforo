<?php

namespace DBTech\Security\Repository;

use XF\Entity\User;
use XF\Finder\UserRememberFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;

class UserRememberRepository extends Repository
{
	/**
	 * @param User|null $user
	 *
	 * @return AbstractCollection
	 */
	public function getRememberedDevicesForUser(?User $user = null): AbstractCollection
	{
		$user = $user ?: \XF::visitor();

		return \XF::app()->finder(UserRememberFinder::class)
			->where('user_id', $user->user_id)
			->setDefaultOrder('start_date', 'asc')
			->fetch()
		;
	}
}
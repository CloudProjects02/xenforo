<?php

namespace XF\Repository;

use XF\Finder\UserRejectFinder;
use XF\Mvc\Entity\Repository;

class UserRejectRepository extends Repository
{
	/**
	 * @return UserRejectFinder
	 */
	public function findUserRejectionsForList()
	{
		return $this->finder(UserRejectFinder::class)
			->with('User')
			->with('RejectUser')
			->setDefaultOrder('reject_date', 'DESC');
	}
}

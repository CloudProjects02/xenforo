<?php

namespace XF\Repository;

use XF\Finder\CronEntryFinder;
use XF\Mvc\Entity\Repository;

class CronEntryRepository extends Repository
{
	/**
	 * @return CronEntryFinder
	 */
	public function findCronEntriesForList()
	{
		return $this->finder(CronEntryFinder::class)
			->with('AddOn')
			->order(['next_run']);
	}
}

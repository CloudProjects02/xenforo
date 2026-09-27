<?php

namespace XF\Repository;

use XF\Finder\FeedFinder;
use XF\Mvc\Entity\Repository;

class FeedRepository extends Repository
{
	/**
	 * @return FeedFinder
	 */
	public function findFeedsForList()
	{
		return $this->finder(FeedFinder::class)->order('title');
	}

	/**
	 * @return FeedFinder
	 */
	public function findDueFeeds($time = null)
	{
		$finder = $this->finder(FeedFinder::class);

		return $finder
			->isDue($time)
			->where('active', true)
			->with(['Forum', 'Forum.Node'], true)
			->order('last_fetch');
	}
}

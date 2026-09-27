<?php

namespace XF\Repository;

use XF\Finder\MemberStatFinder;
use XF\Mvc\Entity\Repository;

class MemberStatRepository extends Repository
{
	/**
	 * @return MemberStatFinder
	 */
	public function findMemberStatsForList()
	{
		return $this->finder(MemberStatFinder::class)
			->order('display_order');
	}

	/**
	 * @return MemberStatFinder
	 */
	public function findMemberStatsForDisplay()
	{
		$finder = $this->finder(MemberStatFinder::class);

		$finder
			->activeOnly()
			->order('display_order')
			->keyedBy('member_stat_key');

		return $finder;
	}

	/**
	 * @return MemberStatFinder
	 */
	public function findCacheableMemberStats()
	{
		$finder = $this->finder(MemberStatFinder::class);

		$finder
			->activeOnly()
			->cacheableOnly()
			->order('member_stat_id');

		return $finder;
	}

	public function emptyCache($memberStatKey)
	{
		$finder = $this->finder(MemberStatFinder::class);

		$memberStat = $finder
			->cacheableOnly()
			->where('member_stat_key', $memberStatKey)
			->order('member_stat_id')
			->fetchOne();

		if (!$memberStat)
		{
			return false;
		}

		$memberStat->cache_results = null;
		$memberStat->cache_expiry = 0;
		$memberStat->save();
		return true;
	}
}

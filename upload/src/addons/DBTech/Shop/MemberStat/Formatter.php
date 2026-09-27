<?php

namespace DBTech\Shop\MemberStat;

use XF\Entity\MemberStat;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\ArrayCollection;

class Formatter
{
	/**
	 * @param MemberStat $memberStat
	 * @param UserFinder $finder
	 *
	 * @return array|ArrayCollection
	 */
	public static function number(MemberStat $memberStat, UserFinder $finder): ArrayCollection|array
	{
		if ($memberStat->show_value)
		{
			$valueField = $memberStat->sort_order;
			$finder->where($valueField, '>', 0);
		}
		else
		{
			$valueField = null;
		}

		$results = $finder->fetch($memberStat->user_limit * 3);

		if ($valueField)
		{
			$results = $results->pluckNamed($valueField, 'user_id');
			$results = array_map(function ($value): string
			{
				return \XF::language()->numberFormat($value);
			}, $results);
		}
		else
		{
			$results = $results->pluck(function (User $user): array
			{
				return [$user->user_id, null];
			}, false);
		}
		return $results;
	}
}
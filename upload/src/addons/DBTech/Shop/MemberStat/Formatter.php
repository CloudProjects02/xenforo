<?php

namespace DBTech\Shop\MemberStat;

use XF\Entity\MemberStat;
use XF\Finder\User;

/**
 * Class Formatter
 *
 * @package DBTech\Shop\MemberStat
 */
class Formatter
{
	/**
	 * @param MemberStat $memberStat
	 * @param User $finder
	 *
	 * @return array|\XF\Mvc\Entity\ArrayCollection
	 */
	public static function number(MemberStat $memberStat, User $finder)
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
			$results = $results->pluck(function (\XF\Entity\User $user): array
			{
				return [$user->user_id, null];
			}, false);
		}
		return $results;
	}
}
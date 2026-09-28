<?php

namespace XC\RankingSystem\MemberStat;

use XF\Entity\MemberStat;
use XF\Finder\User;

class Formatter
{
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
			$results = array_map(function (float $value): string
			{

				return \XF::language()->shortNumberFormat($value);
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
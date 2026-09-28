<?php

namespace EAEAddons\ThreadCount;

use XF\Container;

class Listener
{
	public static function userMergeCombine(
		\XF\Entity\User $target, \XF\Entity\User $source, \XF\Service\User\Merge $mergeService)
	{
		$target->eaetc_thread_count += $source->eaetc_thread_count;
	}

	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		$sortOrders['eaetc_thread_count'] = \XF::phrase('eae_tc_thread_count');
	}

	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'thread_count':
				if (isset($user->eaetc_thread_count) && $user->eaetc_thread_count >= $data['threads'])
				{
					$returnValue = true;
				}
			break;
			case 'thread_maximum':
				if (isset($user->eaetc_thread_count) && $user->eaetc_thread_count <= $data['threads'])
				{
					$returnValue = true;
				}
			break;
		}
	}
}
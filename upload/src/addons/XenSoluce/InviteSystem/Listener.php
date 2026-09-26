<?php

namespace XenSoluce\InviteSystem;

class Listener
{
   	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		switch ($rule)
		{
            case 'min_invite':
                if (isset($user->xs_is_invite_count) && $user->xs_is_invite_count >= $data['invite'])
                {
                    $returnValue = true;
                }
                break;
            case 'max_invite':
                if (isset($user->xs_is_invite_count) && $user->xs_is_invite_count <= $data['invite'])
                {
                    $returnValue = true;
                }
                break;
		}
	}
	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		$sortOrders['xs_is_invite_count'] = \XF::phrase('xs_is_most_invitations');
	}
}

<?php

namespace XenDev\StaffManager\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XenDev\StaffManager\Entity\StaffBlock as StaffBlockEntity;

class Member extends XFCP_Member
{
    public function actionIndex(ParameterBag $params)
    {
        $key = $this->filter('key', 'str');

        if ($key === 'staff_members')
        {
            return $this->actionStaffMembers();
        }

        return parent::actionIndex($params);
    }

    protected function actionStaffMembers()
{
    $blocks = $this->finder('XenDev\StaffManager:StaffBlock')
        ->where('active', 1)
        ->order('display_order')
        ->fetch();

    $staffBlocks = [];

    foreach ($blocks as $block)
    {
        $staffBlocks[] = [
            'block' => $block,
            'members' => $this->getBlockMembers($block)
        ];
    }

   
    $memberStats = \XF::app()->repository('XF:MemberStat')->findMemberStatsForDisplay()->fetch();

    return $this->view(
        'XenDev\StaffManager:Member\StaffMembers',
        'xendev_staffmanager_members',
        [
            'staffBlocks' => $staffBlocks,
            'memberStats' => $memberStats, 
            'pageSelected' => 'staff_members'
        ]
    );
}

    protected function getBlockMembers(StaffBlockEntity $block)
    {
        switch ($block->source_type)
        {
            case 'user_group':
                return $this->getUserGroupMembers($block);

            case 'admin':
                return $this->getUsersByIds($this->getAdminUserIds());

            case 'global_mod':
                return $this->getUsersByIds($this->getGlobalModeratorUserIds());

            case 'node_mod':
    return $this->getUsersByIds($this->getNodeModeratorUserIds($block));

            default:
                return [];
        }
    }

    protected function getUserGroupMembers(StaffBlockEntity $block)
    {
        if (!$block->user_group_id)
        {
            return [];
        }

        $finder = $this->finder('XF:User')
            ->with('Profile')
            ->where('user_state', 'valid')
            ->order('username');

        $db = $this->app()->db();

        if ($block->group_match_type === 'primary')
        {
            $finder->where('user_group_id', $block->user_group_id);
            return $finder->fetch();
        }

        if ($block->group_match_type === 'secondary')
        {
            $userIds = $db->fetchAllColumn(
                "
                SELECT user_id
                FROM xf_user
                WHERE FIND_IN_SET(?, secondary_group_ids)
                ",
                [$block->user_group_id]
            );

            if (!$userIds)
            {
                return [];
            }

            $finder->where('user_id', $userIds);
            return $finder->fetch();
        }

        $userIds = $db->fetchAllColumn(
            "
            SELECT user_id
            FROM xf_user
            WHERE user_group_id = ?
               OR FIND_IN_SET(?, secondary_group_ids)
            ",
            [$block->user_group_id, $block->user_group_id]
        );

        if (!$userIds)
        {
            return [];
        }

        $finder->where('user_id', $userIds);
        return $finder->fetch();
    }

    protected function getAdminUserIds()
    {
        $db = $this->app()->db();

        $userIds = $db->fetchAllColumn("
            SELECT user_id
            FROM xf_admin
            ORDER BY user_id
        ");

        return array_values(array_unique(array_map('intval', $userIds)));
    }

    protected function getGlobalModeratorUserIds()
    {
        $db = $this->app()->db();

        $userIds = $db->fetchAllColumn("
            SELECT user_id
            FROM xf_moderator
            WHERE is_super_moderator = 1
            ORDER BY user_id
        ");

        return array_values(array_unique(array_map('intval', $userIds)));
    }

    protected function getNodeModeratorUserIds(StaffBlockEntity $block)
{
    if (!$block->node_id)
    {
        return [];
    }

    $db = $this->app()->db();

    $userIds = $db->fetchAllColumn("
        SELECT DISTINCT mc.user_id
        FROM xf_moderator_content AS mc
        INNER JOIN xf_moderator AS m ON (m.user_id = mc.user_id)
        WHERE mc.content_type = 'node'
          AND mc.content_id = ?
          AND m.is_super_moderator = 0
        ORDER BY mc.user_id
    ", [$block->node_id]);

    return array_values(array_unique(array_map('intval', $userIds)));
}

    protected function getUsersByIds(array $userIds)
    {
        if (!$userIds)
        {
            return [];
        }

        return $this->finder('XF:User')
            ->with('Profile')
            ->where('user_id', $userIds)
            ->where('user_state', 'valid')
            ->order('username')
            ->fetch();
    }
}
 		  					 	  		   	  			   	  			  	    	 				     		 			 		 		 	  			     	  	 		 			  	   	   	 		  	    		  			      	      		  

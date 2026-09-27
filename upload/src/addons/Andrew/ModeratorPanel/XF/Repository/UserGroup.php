<?php

namespace Andrew\ModeratorPanel\XF\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserGroup extends XFCP_UserGroup
{
    public function getUserGroupTitlePairsMP()
    {
        $options = $this->app()->options();
        $groupOptions = $options->andrewModeratorPaneUpdateUserGroup;

        return $this->findUserGroupsForList()
            ->where('user_group_id', '=', $groupOptions)
            ->fetch()->pluckNamed('title', 'user_group_id');
    }

}

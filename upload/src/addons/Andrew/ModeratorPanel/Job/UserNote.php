<?php

namespace Andrew\ModeratorPanel\Job;

use Andrew\ModeratorPanel\XF\Entity\User;
use XF\Job\AbstractRebuildJob;


class UserNote extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn(
            $db->limit("SELECT `user_id` FROM `xf_user` WHERE `user_id` > ? ORDER BY `user_id`", $batch),
            $start
        );
    }


    protected function rebuildById($id)
    {

        $user = $this->app->em()->find('XF:User', $id);
        if ($user)
        {
            $user->rebuildAndrewUserNotesCounter();
            $user->save();
        }

    }

    protected function getStatusType()
    {
        return \XF::phrase('users');
    }
}
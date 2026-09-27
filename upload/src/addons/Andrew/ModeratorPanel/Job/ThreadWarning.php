<?php

namespace Andrew\ModeratorPanel\Job;

use Andrew\ModeratorPanel\XF\Entity\Warning;
use XF\Job\AbstractRebuildJob;


class ThreadWarning extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn(
            $db->limit("SELECT `warning_id` FROM `xf_warning` WHERE `warning_id` > ? ORDER BY `warning_id`", $batch),
            $start
        );
    }


    protected function rebuildById($id)
    {

        $warning = $this->app->em()->find('XF:Warning', $id);
        if ($warning)
        {
            $warning->rebuildAndrewThreadWarning();
            $warning->save();
        }

    }

    protected function getStatusType()
    {
        return \XF::phrase('warnings');
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 21/08/2020
 * Time: 17:12
 */

namespace XenConcept\ProfileViews\Job;

use XF\Job\AbstractRebuildJob;

class UserProfile extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            "
				SELECT user_id
				FROM xf_user_profile
				WHERE user_id > ?
				ORDER BY user_id
			", $batch
        ), $start);
    }

    protected function rebuildById($id)
    {
        /** @var \XF\Entity\UserProfile $user */
        $userProfile = $this->app->em()->find('XF:UserProfile', $id);
        if (!$userProfile)
        {
            return;
        }

        $this->app->db()->update('xf_user', ['xc_pv_profile_view_count' => $userProfile->view_count], 'user_id = ?', $id);
    }

    protected function getStatusType()
    {
        return \XF::phrase('xc_profile_views_profile_view_counts');
    }
}
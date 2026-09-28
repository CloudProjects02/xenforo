<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Repository;

use XF\Mvc\Entity\Repository;

class ProfileViews extends Repository
{
    public function findLogProfileViews(\XF\Entity\User $user)
    {
        /** @var \XenConcept\ProfileViews\Finder\ProfileViews $finder */
        $finder = $this->finder('XenConcept\ProfileViews:ProfileViews');

        $userIdsExcluded = $this->getUserIdsExcluded();

        $finder
            ->where('visitor_user_id', '!=', $userIdsExcluded);

        $finder
            ->applyMemberVisibilityRestriction()
            ->where('profile_user_id', $user->user_id)
            ->setDefaultOrder('view_date', 'desc');

        $finder
            ->with('Visitor');

        return $finder;
    }

    public function findLastViewByProfileUserIdAndVisitorUserId($profileUserId, $visitorUserId)
    {
        $finder = $this->finder('XenConcept\ProfileViews:ProfileViews');

        $finder
            ->where('profile_user_id', $profileUserId)
            ->where('visitor_user_id', $visitorUserId);

        $finder
            ->setDefaultOrder('view_date', 'desc');

        return $finder->fetchOne();
    }

    public function findMostProfileViewed()
    {
        $finder = $this->finder('XF:User');

        $userIdsExcluded = $this->getUserIdsExcluded();

        $finder
            ->where('user_id', '!=', $userIdsExcluded);

        $finder
            ->where('xc_pv_profile_view_count', '!=', 0)
            ->setDefaultOrder('xc_pv_profile_view_count', 'desc');

        return $finder;
    }

    public function pruneProfileViews($cutOff = null)
    {
        $options = $this->options();


        if ($options->xc_profile_views_clear_view_logs_after_x_days)
        {
            if ($cutOff === null)
            {
                $cutOff = \XF::$time - 3600 * $options->xc_profile_views_clear_view_logs_after_x_days;
            }

            return $this->db()->delete('xf_xc_profile_views', 'view_date < ?', $cutOff);
        }
    }

    protected function getUserIdsExcluded()
    {
        $userExcluded = $this->options()->xc_profile_views_user_exclude_most_viewer;
        return $userExcluded;
    }
}
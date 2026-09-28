<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Service\User;

use XF\Service\AbstractService;

class ProfileViews extends AbstractService
{
    /**
     * @var \XenConcept\ProfileViews\XF\Entity\User
     */
    protected $profileUser;

    /**
     * @var \XenConcept\ProfileViews\Entity\ProfileViews
     */
    protected $profileView;

    /**
     * @var \XF\Entity\User
     */
    protected $visitor;

    public function __construct(\XF\App $app, \XF\Entity\User $profileUser)
    {
        parent::__construct($app);

        $this->profileUser = $profileUser;

        $this->setupDefaults();
    }

    protected function setupDefaults()
    {

        $this->profileView = $this->profileUser->getNewProfileView();

        $visitor = \XF::visitor();

        $this->profileView->visitor_user_id = $visitor->user_id;

        $this->visitor = $visitor;
    }

    public function getProfileUser()
    {
        return $this->profileUser;
    }

    public function getProfileView()
    {
        return $this->profileView;
    }

    public function save()
    {
        $profileUser =  $this->profileUser;
        $profileView =  $this->profileView;

        if ($profileUser->user_id === $this->visitor->user_id OR $this->visitor->user_id === 0)
        {
            return false;
        }

        $options = \XF::options();

        $cooldownPeriod = $options->xc_profile_views_cooldown_period;

        $profileViewRepo = $this->getProfileViewsRepo();
        $lastViews = $profileViewRepo->findLastViewByProfileUserIdAndVisitorUserId($profileUser->user_id, $this->visitor->user_id);

        if ($lastViews)
        {

            if ($cooldownPeriod)
            {
                $hours = \XF::$time - 3600 * $cooldownPeriod;

                if ($lastViews['view_date'] > $hours)
                {
                    return false;
                }
                else
                {
                    return $this->_processUpdateSave($lastViews);
                }
            }
            else
            {
                return $this->_processUpdateSave($lastViews);
            }
        }
        else
        {
            return $this->_processSave();
        }
    }

    protected function _processUpdateSave(\XenConcept\ProfileViews\Entity\ProfileViews $profileViews)
    {
        $profileViews->view_date = \XF::$time;
        $profileViews->save();
        return true;
    }

    protected function _processSave()
    {
        $profileView = $this->profileView;
        $profileView->preSave();
        $profileView->save();
        return true;
    }

    /**
     * @return \XenConcept\ProfileViews\Repository\ProfileViews
     */
    protected function getProfileViewsRepo()
    {
        return $this->repository('XenConcept\ProfileViews:ProfileViews');
    }
}
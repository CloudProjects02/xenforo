<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2017
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
    protected function setupProfileViewService(\XF\Entity\User $profileUser)
    {
        /** @var \XenConcept\ProfileViews\Service\User\ProfileViews $profileViewService */
        $profileViewService = $this->service('XenConcept\ProfileViews:User\ProfileViews', $profileUser);
        $profileViewService->save();
    }

    public function actionView(ParameterBag $params)
    {
        $response =  parent::actionView($params);

        if ($this->filter('tooltip', 'bool'))
        {
            return $this->rerouteController(__CLASS__, 'tooltip', $params);
        }

        $user = $this->assertViewableUser($params->user_id);

        $this->setupProfileViewService($user);

        return $response;
    }

    public function actionShowViewers(ParameterBag $params)
    {
        /** @var \XenConcept\ProfileViews\XF\Entity\User $user */
        $user = $this->assertViewableUser($params['user_id']);
        if (!$user->canViewUsersViewedProfile($error))
        {
            return $this->noPermission($error);
        }

        $maxItems = 15;

        $profileViewsRepo = $this->getProfileViewsRepo();

        $beforeId = $this->filter('before_id', 'uint');

        $profileViewsFinder = $profileViewsRepo->findLogProfileViews($user);
        $profileViewsFinder->beforeViewId($beforeId);

        $userViewers = $profileViewsFinder->fetch($maxItems * 2);
        $userViewers->slice(0, $maxItems);

        $viewParams = [
            'user' => $user,

            'userViewers' => $userViewers,
            'oldestItemId' => $userViewers->count() ? min(array_keys($userViewers->toArray())) : 0,
            'beforeId' => $beforeId

        ];

        return $this->view('XF:Member\ShowViewers', 'xc_profile_views_show_viewers', $viewParams);
    }

    /**
     * @return \XenConcept\ProfileViews\Repository\ProfileViews
     */
    protected function getProfileViewsRepo()
    {
        return $this->repository('XenConcept\ProfileViews:ProfileViews');
    }

}
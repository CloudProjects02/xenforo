<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\Pub\Controller;

class Member extends XFCP_Member
{
    public function actionGetHideUsers()
    {
        $users = ltrim($this->filter('users', 'str', ['no-trim']));

        if ($users !== '' && utf8_strlen($users) >= 2)
        {
            $users = explode(',', $users);

            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $users    = $userRepo->getUsersByNames($users);

        }
        else
        {
            $users = [];
        }

        $viewParams = [
            'users' => $users
        ];

        return $this->view('XenConcept\HideBBCode:Member\GetHideUsers', '', $viewParams);
    }
}
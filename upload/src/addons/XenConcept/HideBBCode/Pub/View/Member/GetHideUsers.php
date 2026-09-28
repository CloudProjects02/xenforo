<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/
namespace XenConcept\HideBBCode\Pub\View\Member;

use XF\Entity\User;
use XF\Mvc\View;

class GetHideUsers extends View
{
    public function renderJson()
    {
        $userIds = [];

        /** @var User $user */
        foreach ($this->params['users'] AS $user)
        {
            $userIds[] = $user->user_id;
        }

        return [
            'results' => implode(',', $userIds)
        ];
    }
}
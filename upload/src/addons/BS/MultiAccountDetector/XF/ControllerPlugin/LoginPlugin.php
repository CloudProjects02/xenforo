<?php

namespace BS\MultiAccountDetector\XF\ControllerPlugin;

class LoginPlugin extends XFCP_LoginPlugin
{
    public function completeLogin(\XF\Entity\User $user, $remember)
    {
        parent::completeLogin($user, $remember);

        $user->fastUpdate('mad_last_check', 0);
    }
}

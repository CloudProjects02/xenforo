<?php

namespace Andrew\ModeratorPanel\XF\Service\User;

use Andrew\ModeratorPanel\Repository\RecentLoginRepository;
use XF\Entity\User;

use function strlen;

class Login extends XFCP_Login
{
    public function validate($password, &$error = null)
    {
        $parent = parent::validate($password, $error);
        $user = $this->getUser();

        if ($parent && $user !== null)
        {
            if (isset($user->Option) && $user->Option->use_tfa == 0)
            {
                $ipAddress = \XF::app()->request->getIp();
                $country = \XF::repository('XF:User')->getIpCountry($ipAddress);
                $this->repository(RecentLoginRepository::class)
                    ->logToDatabase($user, $ipAddress, $country);
            }
        }

        return $parent;
    }
}
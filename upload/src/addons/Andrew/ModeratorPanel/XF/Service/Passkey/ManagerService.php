<?php

namespace Andrew\ModeratorPanel\XF\Service\Passkey;

use Andrew\ModeratorPanel\Repository\RecentLoginRepository;
use XF\Entity\User;
use XF\Http\Request;
use XF\Service\AbstractService;


class ManagerService extends XFCP_ManagerService
{

    public function validate(Request $request, &$error = null): bool
    {

        $parent = parent::validate($request, $error);
        $status = $parent ? 'successful' : 'failed';

        if (!$this->passkey)
        {
            return false;
        }

        $user = $this->getPasskeyUser();

        if($user && $status == 'successful')
        {
            $ipAddress = $request->getIp();
            $country = \XF::repository('XF:User')->getIpCountry($ipAddress);

            $repo = $this->repository(RecentLoginRepository::class);
            $repo->logToDatabase($user, $ipAddress, $country);
        }

        return $parent;
    }
}
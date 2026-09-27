<?php

namespace Andrew\ModeratorPanel\XF\Service\User;

use Andrew\ModeratorPanel\Repository\RecentLoginRepository;
use XF\Entity\User;

use XF\Http\Request;
use function strlen;

class Tfa extends XFCP_Tfa
{

    public function verify(Request $request, $providerId)
    {

        $parent = parent::verify($request, $providerId);

            if ($parent && $this->user && $providerId != 'passkey')
            {
                $ipAddress = $request->getIp();
                $country = \XF::repository('XF:User')->getIpCountry($ipAddress);
                $this->repository(RecentLoginRepository::class)
                    ->logToDatabase($this->user, $ipAddress, $country);
            }

        return $parent;
    }

}
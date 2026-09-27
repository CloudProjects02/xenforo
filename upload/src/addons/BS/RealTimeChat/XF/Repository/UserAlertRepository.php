<?php

namespace BS\RealTimeChat\XF\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserAlertRepository extends XFCP_UserAlertRepository
{
    public function userReceivesAlert(\XF\Entity\User $receiver, $senderId, $contentType, $action)
    {
        if ($contentType === 'chat_message'
            && !\XF::options()->rtcEnableAlerts
        ) {
            return false;
        }

        return parent::userReceivesAlert(
            $receiver,
            $senderId,
            $contentType,
            $action
        );
    }
}

<?php

namespace MMO\UserBanInfo\XF\Entity;

class User extends XFCP_User
{
    public function canViewFullProfile(&$error = null)
    {
        $response = parent::canViewFullProfile($error);
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('general', 'viewProfile'))
        {
            return false;
        }

        if ($this->is_banned && $visitor->hasPermission('general', 'mubi_viewBannedProfile'))
        {
            return true;
        }

        return $response;
    }
}
<?php

namespace BS\MultiAccountDetector\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public function canManageMultiAccounts()
    {
        return $this->is_moderator && $this->hasPermission('general', 'editMultiAccounts');
    }

    public function isCheckMultiAccount()
    {
        if ($this->hasPermission('general', 'bypassMultiAccount')) {
            return false;
        }

        if ($this->user_id
            && \XF::$time - $this->mad_last_check >= \XF::options()->madCheckCooldown * 60
        ) {
            return true;
        }

        return false;
    }

    public function getNewFingerprint($fingerprint)
    {
        $finger = $this->_em->create('BS\MultiAccountDetector:Fingerprint');
        $finger->bulkSet(
            [
                'user_id' => $this->user_id,
                'fingerprint' => $fingerprint
            ]
        );

        return $finger;
    }

    public function getNewEvercookie($cookie)
    {
        $evercookie = $this->_em->create('BS\MultiAccountDetector:Evercookie');
        $evercookie->bulkSet(
            [
                'user_id' => $this->user_id,
                'evercookie' => $cookie
            ]
        );

        return $evercookie;
    }

    public function getFingerprints()
    {
        $fingerprints = $this->Fingerprints_;

        return $fingerprints->count() ? array_column($fingerprints->toArray(), 'fingerprint') : [];
    }

    public function getEvercookies()
    {
        if (! $this->user_id) {
            return [];
        }

        $evercookies = $this->Evercookies_;

        if (! $evercookies->count()) {
            $evercookie = $this->getNewEvercookie(md5($this->user_id));
            $evercookie->save();

            return [$evercookie->evercookie];
        }

        return array_column($evercookies->toArray(), 'evercookie');
    }
}
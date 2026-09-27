<?php

namespace BS\MultiAccountDetector\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int multi_account_id
 * @property int user_id
 * @property bool is_closed
 * @property int multi_account_date
 * @property string close_reason
 *
 * GETTERS
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\MultiAccountDetector\Entity\Fingerprint[] FingerprintUsers
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\MultiAccountDetector\Entity\Fingerprint[] FingerprintJSUsers
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\MultiAccountDetector\Entity\Fingerprint[] FingerprintJSProUsers
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\MultiAccountDetector\Entity\Evercookie[] EvercookieUsers
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class MultiAccount extends Entity
{
    public function canClose(): bool
    {
        return ! $this->is_closed
            && \XF::visitor()->canManageMultiAccounts();
    }

    public function canOpen(): bool
    {
        return $this->is_closed
            && \XF::visitor()->canManageMultiAccounts();
    }

    public function getFingerprintUsers()
    {
        $userFingerprints = $this->User->Fingerprints;

        return $this->finder('BS\MultiAccountDetector:Fingerprint')
            ->fingerNotUser($this->User, array_column($userFingerprints->toArray(), 'fingerprint'))
            ->with('User')
            ->keyedBy('user_id')
            ->fetch();
    }

    public function getFingerprintJSUsers()
    {
        return $this->FingerprintUsers
            ->filter(fn(Fingerprint $fingerprint) => ! $fingerprint->is_pro);
    }

    public function getFingerprintJSProUsers()
    {
        return $this->FingerprintUsers
            ->filter(fn(Fingerprint $fingerprint) => $fingerprint->is_pro);
    }

    public function getEvercookieUsers()
    {
        $userEvercookies = $this->User->Evercookies;

        return $this->finder('BS\MultiAccountDetector:Evercookie')
            ->cookieNotUser($this->User, array_column($userEvercookies->toArray(), 'evercookie'))
            ->with('User')
            ->keyedBy('user_id')
            ->fetch();
    }

    protected function _preSave()
    {
        $this->getMultiAccountRepo()->rebuildMultiAccountsCache();
    }

    protected function _postSave()
    {
        $repo = $this->getMultiAccountRepo();
        $repo->rebuildMultiAccountsCache();

        if ($this->isInsert() && $this->app()->options()->madThreadReportForum) {
            $this->clearRelationsCacheForReport();
            $this->_createThreadReport();
            $this->assignDetectionUserGroups();
        }
    }

    protected function _createThreadReport()
    {
        try {
            $repo = $this->getMultiAccountRepo();
            $repo->createThreadReport($this);
        } catch (\Exception $e) {
            \XF::logException($e, false, 'MAD thread report creation error: ');
        }
    }

    protected function assignDetectionUserGroups(): void
    {
        $repo = $this->getMultiAccountRepo();
        $repo->assignDetectionUserGroups($this);
    }

    public function clearRelationsCacheForReport()
    {
        $this->User->clearCache('Fingerprints');
        $this->User->clearCache('Evercookies');
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_mad_user_multi_account';
        $structure->shortName = 'BS\MultiAccountDetector:MultiAccount';
        $structure->primaryKey = 'multi_account_id';
        $structure->columns = [
            'multi_account_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'is_closed' => ['type' => self::BOOL, 'default' => false],
            'multi_account_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'close_reason' => ['type' => self::STR, 'default' => '']
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];
        $structure->getters = [
            'FingerprintUsers' => true,
            'FingerprintJSUsers' => true,
            'FingerprintJSProUsers' => true,
            'EvercookieUsers' => true
        ];

        return $structure;
    }

    protected function getMultiAccountRepo()
    {
        return $this->repository('BS\MultiAccountDetector:MultiAccount');
    }
}
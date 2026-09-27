<?php

namespace BS\MultiAccountDetector\Repository;

use BS\MultiAccountDetector\Support\Finder;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class MultiAccount extends Repository
{
    public function findMultiAccounts($timeFrame = null)
    {
        return $this->finder('BS\MultiAccountDetector:MultiAccount')
            ->inTimeFrame($timeFrame)
            ->order(['multi_account_date', 'DESC']);
    }

    public function findSameFingerprintOrCreate(User $user, string $value): bool
    {
        $fingerprint = $this->finder('BS\MultiAccountDetector:Fingerprint')
            ->fingerNotUser($user, $value)
            ->fetchOne();

        if (! in_array($value, $user->fingerprints, true)) {
            $newFingerprint = $user->getNewFingerprint($value);
            $newFingerprint->save();
        }

        return $fingerprint
            && $this->options()->madEnableReports
            && ! $user->MultiAccount;
    }

    public function findSameEvercookieOrCreate(User $user, string $value): bool
    {
        $evercookie = $this->finder('BS\MultiAccountDetector:Evercookie')
            ->cookieNotUser($user, $value)
            ->fetchOne();

        if (! in_array($value, $user->evercookies, true)) {
            $newEvercookie = $user->getNewEvercookie($value);
            $newEvercookie->save();
        }

        return $evercookie
            && $this->options()->madEnableReports
            && ! $user->MultiAccount;
    }

    public function isAlreadyReportedOnAnotherUsers(User $user): bool
    {
        $sameFingerprintUserIds = array_unique(
            Finder::fetchColumn(
                $this->finder('BS\MultiAccountDetector:Fingerprint')
                    ->fingerNotUser($user, $user->fingerprints),
                'user_id'
            )
        );
        $sameEvercookieUserIds = array_unique(
            Finder::fetchColumn(
                $this->finder('BS\MultiAccountDetector:Evercookie')
                    ->cookieNotUser($user, $user->evercookies),
                'user_id'
            )
        );

        $usersWithSameFingerprints = $this->finder('XF:User')
            ->with('MultiAccount')
            ->whereIds($sameFingerprintUserIds)
            ->where('MultiAccount.multi_account_id', '!=', null)
            ->total();
        $usersWithSameEvercookies = $this->finder('XF:User')
            ->with('MultiAccount')
            ->whereIds($sameEvercookieUserIds)
            ->where('MultiAccount.multi_account_id', '!=', null)
            ->total();

        return $usersWithSameFingerprints || $usersWithSameEvercookies;
    }

    public function createMultiAccountReport(
        User $user,
        bool $skipIfAlreadyHasOpenedReport = false
    ) {
        if ($skipIfAlreadyHasOpenedReport && $user->MultiAccount->isOpened()) {
            return false;
        }

        /** @var \BS\MultiAccountDetector\Entity\MultiAccount $multiAccount */
        $multiAccount = $this->em->create('BS\MultiAccountDetector:MultiAccount');
        $multiAccount->user_id = $user->user_id;
        $multiAccount->save();

        $this->rebuildMultiAccountsCache();

        return true;
    }

    public function createThreadReport(\BS\MultiAccountDetector\Entity\MultiAccount $report)
    {
        $forum = $this->em->find('XF:Forum', $this->options()->madThreadReportForum);
        if (! $forum) {
            return;
        }

        /** @var \BS\MultiAccountDetector\Service\Thread\ReportDuplicate $service */
        $service = $this->app()->service(
            'BS\MultiAccountDetector:Thread\ReportDuplicate',
            $report,
            $forum
        );
        $service->create();
    }

    public function assignDetectionUserGroups(\BS\MultiAccountDetector\Entity\MultiAccount $report)
    {
        $options = $this->options();
        $fingerprintDetectionUserGroup = $options->madFingerprintDetectedUserGroup;
        $evercookieDetectionUserGroup = $options->madEvercookieDetectedUserGroup;

        $fingerprintUsers = $options->madFingerprintProToken
            ? $report->FingerprintJSProUsers
            : $report->FingerprintJSUsers;
        if ($fingerprintDetectionUserGroup && $fingerprintUsers->count()) {
            $assignUsers = [$report->User, ...$fingerprintUsers->pluckNamed('User')->toArray()];
            $this->_assignUsersToGroupWithChecksPresence($assignUsers, $fingerprintDetectionUserGroup);
        }

        if ($evercookieDetectionUserGroup && $report->EvercookieUsers->count()) {
            $assignUsers = [$report->User, ...$report->EvercookieUsers->pluckNamed('User')->toArray()];
            $this->_assignUsersToGroupWithChecksPresence($assignUsers, $evercookieDetectionUserGroup);
        }
    }

    protected function _assignUsersToGroupWithChecksPresence(array $users, int $userGroupId)
    {
        /** @var User $user */
        foreach ($users as $user) {
            if ($user->isMemberOf($userGroupId)) {
                continue;
            }
            $user->secondary_group_ids = [$userGroupId, ...$user->secondary_group_ids];
            $user->save();
        }
    }

    public function getMultiAccountsCacheData()
    {
        $finder = $this->findMultiAccounts()->onlyOpen();

        $cache = [
            'opened' => $finder->total()
        ];

        return $cache;
    }

    public function rebuildMultiAccountsCache()
    {
        $cache = $this->getMultiAccountsCacheData();
        \XF::registry()->set('multiAccountCache', $cache);
        return $cache;
    }
}
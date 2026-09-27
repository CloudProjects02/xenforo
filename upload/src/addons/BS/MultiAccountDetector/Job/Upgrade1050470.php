<?php

namespace BS\MultiAccountDetector\Job;

use XF\Job\AbstractJob;
use XF\Timer;

class Upgrade1050470 extends AbstractJob
{
    // 1.5.0 release date
    private const PROBLEM_UPGRADE_RELEASE_DATE = 1656443537;

    protected $defaultData = [
        'from' => self::PROBLEM_UPGRADE_RELEASE_DATE,
        'max'  => 0
    ];

    public function run($maxRunTime)
    {
        $timer = new Timer($maxRunTime);

        while ($this->restoreLostReports($this->data['from']) !== true) {
            if ($timer->limitExceeded()) {
                return $this->resume();
            }
        }

        return $this->complete();
    }

    /**
     * @param  int  $from  timestamp from which to restore reports
     * @return bool|null
     */
    protected function restoreLostReports(int $from)
    {
        /** @var \BS\MultiAccountDetector\Repository\MultiAccount $repo */
        $repo = \XF::repository('BS\MultiAccountDetector:MultiAccount');
        $em = \XF::em();

        $reportOnNewUser = \XF::options()->madReportOnNewUser;

        $perPage = 100;

        /** @var \XF\Db\AbstractAdapter $db */
        $db = \XF::db();
        $db->beginTransaction();

        if (! $this->data['max']) {
            $maxEvercookieDate = $db->fetchOne(
                'SELECT MAX(evercookie_date) FROM xf_mad_user_evercookie'
            );
            $maxFingerprintDate = $db->fetchOne(
                'SELECT MAX(fingerprint_date) FROM xf_mad_user_fingerprint'
            );
            $this->data['max'] = max($maxEvercookieDate, $maxFingerprintDate);
        }

        $fingerprintRecords = $db->fetchAll(
            'SELECT fingerprint, user_id, fingerprint_date
            FROM xf_mad_user_fingerprint 
            WHERE fingerprint_date > ? 
            ORDER BY fingerprint_date DESC 
            LIMIT ?',
            [$from, $perPage]
        );
        $evercookieRecords = $db->fetchAll(
            'SELECT evercookie, user_id, evercookie_date
            FROM xf_mad_user_evercookie 
            WHERE evercookie_date > ? 
            ORDER BY evercookie_date DESC 
            LIMIT ?',
            [$from, $perPage]
        );

        if (! $fingerprintRecords && ! $evercookieRecords) {
            $db->commit();
            return true;
        }

        $reported = [];

        foreach ($fingerprintRecords as $fingerprintRecord) {
            $user = $em->find('XF:User', $fingerprintRecord['user_id']);
            if (! $user) {
                continue;
            }

            $shouldReport = false;
            $shouldReport |= $repo->findSameFingerprintOrCreate($user, $fingerprintRecord['fingerprint']);

            $this->clearUserMadRecordsCache($user);

            if (! $reportOnNewUser) {
                $shouldReport &= !$repo->isAlreadyReportedOnAnotherUsers($user);
            }

            if ($shouldReport) {
                $repo->createMultiAccountReport($user);
                $reported[] = $user->user_id;
            }
        }

        foreach ($evercookieRecords as $evercookieRecord) {
            $user = $em->find('XF:User', $evercookieRecord['user_id']);
            if (! $user) {
                continue;
            }

            $shouldReport = false;
            $shouldReport |= $repo->findSameEvercookieOrCreate($user, $evercookieRecord['evercookie']);

            $this->clearUserMadRecordsCache($user);

            if (! $reportOnNewUser) {
                $shouldReport &= !$repo->isAlreadyReportedOnAnotherUsers($user);
            }

            if ($shouldReport && ! in_array($user->user_id, $reported, true)) {
                $repo->createMultiAccountReport($user);
            }
        }

        $dates = array_merge(
            array_column($fingerprintRecords, 'fingerprint_date'),
            array_column($evercookieRecords, 'evercookie_date')
        );

        $this->data['from'] = max($dates);

        $db->commit();

        return null;
    }

    protected function clearUserMadRecordsCache($user): void
    {
        $keys = ['fingerprints', 'evercookies', 'Fingerprints', 'Evercookies'];
        foreach ($keys as $key) {
            $user->clearCache($key);
        }
    }

    public function canTriggerByChoice()
    {
        return true;
    }

    public function canCancel()
    {
        return false;
    }

    public function getStatusMessage()
    {
        return 'Upgrade 1.5.3';
    }
}
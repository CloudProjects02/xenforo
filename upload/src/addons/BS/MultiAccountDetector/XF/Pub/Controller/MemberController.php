<?php

namespace BS\MultiAccountDetector\XF\Pub\Controller;

use BS\MultiAccountDetector\Support\Finder;
use XF\Mvc\ParameterBag;

class MemberController extends XFCP_MemberController
{
    public function actionFingerprints(ParameterBag $params)
    {
        if (! \XF::visitor()->canManageMultiAccounts()) {
            return $this->noPermission();
        }

        $user = $this->assertViewableUser($params->user_id);

        $perPage = 15;
        $page = $params->page;

        $fingerprintFinder = $this->finder('BS\MultiAccountDetector:Fingerprint')
            ->fingerNotUser($user, array_column($user->Fingerprints->toArray(), 'fingerprint'))
            ->with('User')
            ->keyedBy('user_id')
            ->order('fingerprint_date', 'desc')
            ->limitByPage($page, $perPage);

        $viewParams = [
            'user' => $user,

            'fingerprints' => $fingerprintFinder->fetch(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $fingerprintFinder->total()
        ];

        return $this->view('BS\MultiAccountDetector:Member\Fingerprints', 'mad_member_fingerprints', $viewParams);
    }

    public function actionEvercookies(ParameterBag $params)
    {
        if (! \XF::visitor()->canManageMultiAccounts()) {
            return $this->noPermission();
        }

        $user = $this->assertViewableUser($params->user_id);

        $perPage = 15;
        $page = $params->page;

        $evercookieFinder = $this->finder('BS\MultiAccountDetector:Evercookie')
            ->cookieNotUser($user, array_column($user->Evercookies->toArray(), 'evercookie'))
            ->with('User')
            ->keyedBy('user_id')
            ->order('evercookie_date', 'desc')
            ->limitByPage($page, $perPage);

        $viewParams = [
            'user' => $user,

            'evercookies' => $evercookieFinder->fetch(),

            'page' => $page,
            'perPage' => $perPage,
            'total' => $evercookieFinder->total()
        ];

        return $this->view('BS\MultiAccountDetector:Member\Evercookies', 'mad_member_evercookies', $viewParams);
    }

    public function actionMadCheck()
    {
        $user = \XF::visitor();
        $user->fastUpdate('mad_last_check', \XF::$time);

        $redirect = $this->redirect($this->buildLink('members'), '');

        $fingerprint = $this->filter('fpJs', 'str');
        $evercookies = array_unique($this->filter('ec', 'array-str'));

        // Check multi accounts only for authorised users
        if (! $user->user_id) {
            return $redirect;
        }

        $repo = $this->getMultiAccountRepo();

        $shouldReport = false;

        // Report if another user already has same fingerprint or evercookie
        if ($fingerprint) {
            $shouldReport |= $repo->findSameFingerprintOrCreate($user, $fingerprint);
        }
        foreach ($evercookies as $evercookie) {
            $shouldReport |= $repo->findSameEvercookieOrCreate($user, $evercookie);
        }

        if (! $this->options()->madReportOnNewUser) {
            $shouldReport &= !$repo->isAlreadyReportedOnAnotherUsers($user);
        }

        if ($shouldReport) {
            $repo->createMultiAccountReport($user);
        }

        return $redirect;
    }

    /** @return \BS\MultiAccountDetector\Repository\MultiAccount */
    protected function getMultiAccountRepo()
    {
        return $this->repository('BS\MultiAccountDetector:MultiAccount');
    }
}

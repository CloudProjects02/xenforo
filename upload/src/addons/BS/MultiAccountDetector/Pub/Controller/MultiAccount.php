<?php

namespace BS\MultiAccountDetector\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class MultiAccount extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        if (! \XF::visitor()->canManageMultiAccounts()) {
            throw $this->exception($this->noPermission());
        }
    }

    public function actionIndex()
    {
        return $this->showMultiAccountList();
    }

    public function actionClosed()
    {
        return $this->showMultiAccountList(false);
    }

    protected function showMultiAccountList(bool $opened = true)
    {
        $multiAccountRepo = $this->getMultiAccountRepo();

        $page = $this->filterPage();
        $perPage = 15;

        $reportsFinder = $multiAccountRepo->findMultiAccounts()
            ->onlyOpen($opened)
            ->limitByPage($page, $perPage);

        $reports = $reportsFinder->fetch();
        $total = $reportsFinder->total();

        if ($total !== $this->app->multiAccountCache['opened']) {
            $multiAccountRepo->rebuildMultiAccountsCache();
        }

        $activeTab = $opened ? 'open': 'closed';

        return $this->view(
            'BS\MultiAccountDetector:MultiAccount\Listing',
            'multi_account_list',
            compact('reports', 'total', 'page', 'perPage', 'activeTab')
        );
    }

    public function actionProcess()
    {
        $this->assertPostOnly();

        /** @var \BS\MultiAccountDetector\Service\ReportQueue $service */
        $service = $this->service('BS\MultiAccountDetector:ReportQueue');
        $service->runJob($this->filter('queue', 'array'));

        return $this->redirect($this->buildLink('multi-accounts'));
    }

    /**
     * @return \BS\MultiAccountDetector\Repository\MultiAccount
     */
    protected function getMultiAccountRepo()
    {
        return $this->repository('BS\MultiAccountDetector:MultiAccount');
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('performing_moderation_duties');
    }
}
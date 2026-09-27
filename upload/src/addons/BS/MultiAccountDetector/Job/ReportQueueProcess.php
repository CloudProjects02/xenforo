<?php

namespace BS\MultiAccountDetector\Job;

use XF\Job\AbstractJob;
use XF\Timer;

class ReportQueueProcess extends AbstractJob
{
    protected $defaultData = [
        'asUserId' => 0,
        'queue' => [],
        'rawInput' => []
    ];

    public function run($maxRunTime)
    {
        $timer = new Timer($maxRunTime);

        /** @var \BS\MultiAccountDetector\Service\ReportQueue $service */
        $service = $this->app->service('BS\MultiAccountDetector:ReportQueue');
        $asUser = $this->getAsUser();

        foreach ($this->data['queue'] AS $id => $action) {
            if ($report = $this->findReport($id)) {
                \XF::asVisitor(
                    $asUser,
                    fn() => $service->handleAction($report, $action)
                );
            }

            unset($this->data['queue'][$id]);

            if ($timer->limitExceeded()) {
                return $this->resume();
            }
        }

        return $this->complete();
    }

    /**
     * @return \XF\Entity\User
     */
    protected function getAsUser()
    {
        return $this->app->em()->find('XF:User', $this->data['asUserId'])
            ?? $this->app->repository('XF:User')->getGuestUser();
    }

    /**
     * @param  int  $id
     * @return \BS\MultiAccountDetector\Entity\MultiAccount
     */
    protected function findReport(int $id)
    {
        return $this->app->em()->find('BS\MultiAccountDetector:MultiAccount', $id);
    }

    public function getStatusMessage()
    {
        return \XF::phrase('mad_processing_reports...');
    }

    public function canTriggerByChoice()
    {
        return true;
    }

    public function canCancel()
    {
        return false;
    }
}
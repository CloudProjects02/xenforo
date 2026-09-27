<?php

namespace BS\MultiAccountDetector\Service;

use BS\MultiAccountDetector\Entity\MultiAccount;
use XF\Service\AbstractService;

class ReportQueue extends AbstractService
{
    private const ACTION_CLOSE = 'delete';
    private const ACTION_OPEN  = 'open';

    public function handleAction(MultiAccount $report, array $action)
    {
        switch ($action['action'] ?? '') {
            case self::ACTION_CLOSE:
                $this->updateReportClose(
                    $report,
                    true,
                    $action['close_reason'] ?? ''
                );
                break;

            case self::ACTION_OPEN:
                $this->updateReportClose($report, false);
                break;
        }
    }

    protected function updateReportClose(
        MultiAccount $report,
        bool $close,
        string $reason = ''
    ) {
        $report->bulkSet([
            'is_closed' => $close,
            'close_reason' => $reason
        ]);
        $report->save();
    }

    public function runJob(array $queue)
    {
        $jobManager = $this->app->jobManager();
        $jobManager->enqueueAutoBlocking('BS\MultiAccountDetector:ReportQueueProcess', [
            'asUserId' => \XF::visitor()->user_id,
            'queue'    => $this->removeInvalidActions($queue)
        ]);
        $jobManager->setAutoBlockingMessage(\XF::phrase('mad_processing_reports...'));
    }

    protected function removeInvalidActions(array $queue)
    {
        return array_filter(
            $queue,
            fn($item) => $this->isValidAction($item['action'] ?? '')
        );
    }

    protected function isValidAction(string $action)
    {
        return in_array($action, [
            self::ACTION_OPEN,
            self::ACTION_CLOSE
        ]);
    }
}
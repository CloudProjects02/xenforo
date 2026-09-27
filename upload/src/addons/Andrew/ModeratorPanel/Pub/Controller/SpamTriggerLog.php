<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class SpamTriggerLog extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewSpamTriggerLog()) {
            return $this->noPermission();
        }

        if ($params->trigger_log_id)
        {
            return $this->rerouteController(__CLASS__, 'spamTriggerLogView', $params);
        }

        $page = $this->filterPage();
        $perPage = 20;

        /** @var \XF\Repository\Spam $spamRepo */
        $spamRepo = $this->repository('XF:Spam');

        $logFinder = $spamRepo->findSpamTriggerLogsForList()
            ->limitByPage($page, $perPage);

        $viewParams = [
            'entries' => $logFinder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $logFinder->total()
        ];
        return $this->view('Andrew\ModeratorPanel\SpamTriggerLog\Listing:View', 'andrew_moderatorpanel_spam_trigger_list', $viewParams);
    }

    public function actionSpamTriggerLogView(ParameterBag $params)
    {
        $entry = $this->assertSpamTriggerLogExists($params->trigger_log_id, null, 'requested_log_entry_not_found');

        $viewParams = [
            'entry' => $entry
        ];
        return $this->view('Andrew\ModeratorPanel\SpamTriggerLog:View', 'andrew_moderatorpanel_log_spam_trigger_view', $viewParams);
    }

    protected function assertSpamTriggerLogExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XF:SpamTriggerLog', $id, $with, $phraseKey);
    }
}
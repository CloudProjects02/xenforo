<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class SpamCleanerLog extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewSpamCleanerLog()) {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 20;

        $repo = $this->repository('XF:Spam');
        $finder = $repo->findSpamCleanerLogsForList()
            ->limitByPage($page, $perPage);

        $viewParams = [
            'entries' => $finder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $finder->total()
        ];
        return $this->view('Andrew\ModeratorPanel\SpamCleanerLog\Listing:View', 'andrew_moderatorpanel_spam_cleaner_list', $viewParams);
    }
}
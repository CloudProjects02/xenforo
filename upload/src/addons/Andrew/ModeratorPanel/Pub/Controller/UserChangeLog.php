<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class UserChangeLog extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewChangeLog())
        {
            return $this->noPermission();
        }

        $this->setSectionContext('userChangeLog');

        $page = $this->filterPage();
        $perPage = 20;

        $changeRepo = $this->repository('XF:ChangeLog');
        $changeFinder = $changeRepo->findChangeLogsByContentType('user')->limitByPage($page, $perPage);

        $changes = $changeFinder->fetch();
        $changeRepo->addDataToLogs($changes);

        $viewParams = [
            'changesGrouped' => $changeRepo->groupChangeLogs($changes),
            'totalChanges' => count($changes),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $changeFinder->total(),
        ];
        return $this->view('Andrew\ModeratorPanel\UserChangeLog:View', 'andrew_moderatorpanel_user_change_log', $viewParams);
    }
}
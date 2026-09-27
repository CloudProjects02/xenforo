<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class UsernameChangeLog extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewUsernameChangeLog()) {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 20;

        $usernameChangeRepo = $this->repository('XF:UsernameChange');
        $entryFinder = $usernameChangeRepo->findUsernameChangesForList()
            ->limitByPage($page, $perPage);

        $entries = $entryFinder->fetch();

        $viewParams = [
            'entries' => $entries,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $entryFinder->total()
        ];
        return $this->view('Andrew\ModeratorPanel\UsernameChangeLog\Listing:View', 'andrew_moderatorpanel_user_name_change_list', $viewParams);
    }
}
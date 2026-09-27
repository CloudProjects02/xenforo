<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ModeratorLog extends AbstractController
{
    public function actionIndex()
    {

        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewModeratorLog())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 20;

        $repo = $this->repository('XF:ModeratorLog');

        $finder = $repo->findLogsForList()
            ->limitByPage($page, $perPage);

        $linkFilters = [];
        if ($userId = $this->filter('user_id', 'uint'))
        {
            $linkFilters['user_id'] = $userId;
            $finder->where('user_id', $userId);
        }

        if ($this->isPost())
        {
            // redirect to give a linkable page
            return $this->redirect($this->buildLink('moderatorpanel/moderator-log', null, $linkFilters));
        }

        $viewParams = [
            'entries' => $finder->fetch(),
            'logUsers' => $repo->getUsersInLog(),
            'userId' => $userId,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $finder->total(),
            'linkFilters' => $linkFilters
        ];
        return $this->view('Andrew\ModeratorPanel\ModeratorLog:View', 'andrew_moderatorpanel_log_moderator_list', $viewParams);
    }
}
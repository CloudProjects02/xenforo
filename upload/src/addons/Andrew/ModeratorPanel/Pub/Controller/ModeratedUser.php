<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ModeratedUser extends AbstractController
{
    public function actionIndex()
    {
        $options = $this->app()->options();
        $moderatedUsers = $options->andrewModeratorPanelModeratedUsergroup;

        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratedUsers() || $moderatedUsers == 0)
        {
            return $this->noPermission();
        }

        $filterInput = $this->filter([
            'username' => 'str',
        ]);

        $username = $filterInput['username'] ?? null;

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:User');
        $inSet = $finder->expression("FIND_IN_SET(". $moderatedUsers .", secondary_group_ids)");
        $finder->where($inSet);

        if($username)
        {
            $finder->where('username',$username);
        }

        $total = $finder->total();
        $finder->limitByPage($page, $perPage);

        $users = $finder->fetch();

        $viewParams = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'users' => $users,
            'filters' => $filterInput,
            'userFilter' => $username
        ];

        return $this->view('Andrew\ModeratorPanel\ModeratedUsers:View', 'andrew_moderatorpanel_moderatedusers_view',$viewParams);
    }
}
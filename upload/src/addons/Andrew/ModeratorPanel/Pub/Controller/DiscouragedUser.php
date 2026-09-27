<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class DiscouragedUser extends AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewDiscouragedUsersListMP())
        {
            return $this->noPermission();
        }

        $filterInput = $this->filter([
            'username' => 'str',
        ]);

        $username = $filterInput['username'] ?? null;

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:UserOption')
            ->with('User')
            ->where('is_discouraged', 1);

        if($username)
        {
            $finder->where('User.username', $username);
        }

        $users = $finder->order('User.username', 'ASC')->limitByPage($page, $perPage)->fetch()->toArray();

        $total = $finder->total();
        $finder->limitByPage($page, $perPage);;

        $users = array_filter(array_column($users, 'User'));

        $viewParams = [
            'total' =>  $total,
            'page' => $page,
            'perPage' => $perPage,
            'userFilter' => $username,
            'users' => $users,
            'filters' => $filterInput
        ];

        return $this->view('Andrew\ModeratorPanel\DiscouragedUsers:View', 'andrew_moderatorpanel_discouragedusers_view',$viewParams);
    }
}

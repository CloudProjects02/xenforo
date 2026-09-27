<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class WarnedUser extends AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewWarnedUsers())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $filterInput = $this->filter([
            'username' => 'str',
            'given_by' => 'str',
            'order' => 'str',
            'sortby' => 'str'
        ]);

        $username = $filterInput['username'] ?? null;
        $givenBy = $filterInput['given_by'] ?? null;
        $order = $filterInput['order'] ?? 'desc';
        $sortby = $filterInput['sortby'] ?? 'date';

        $finder = \XF::finder('XF:Warning')
            ->with('User');

        if ($username)
        {
            $finder->where('User.username', $username);
        }

        if ($givenBy)
        {
            $finder->where('WarnedBy.username', $givenBy);
        }

        switch ($sortby) {
            case 'username':
                $finder->order('User.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'warned_by':
                $finder->order('WarnedBy.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'expiry_date':
                $finder->order('expiry_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'warning_date':
            default:
                $finder->order('warning_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
        }

        $total = $finder->total();
        $finder->limitByPage($page, $perPage);;

        $warnedUsers = $finder->fetch();

        $viewParams = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'warnedUsers' => $warnedUsers,
            'filters' => $filterInput,
            'userFilter' => $username,
            'givenByFilter' => $givenBy
        ];

        return $this->view('Andrew\ModeratorPanel\WarnedUsers:View', 'andrew_moderatorpanel_warnedusers_view',$viewParams);
    }
}
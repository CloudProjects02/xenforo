<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ForceIgnoredUser extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewForceIgnoreMP())
        {
            return $this->noPermission();
        }

        $filterInput = $this->filter([
            'username' => 'str',
            'ignoring' => 'str',
            'given_by' => 'str',
            'order' => 'str',
            'sortby' => 'str'
        ]);

        $username = $filterInput['username'] ?? null;
        $ignoring = $filterInput['ignoring'] ?? null;
        $givenBy = $filterInput['given_by'] ?? null;
        $order = $filterInput['order'] ?? 'desc';
        $sortby = $filterInput['sortby'] ?? 'date';

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:UserIgnored')
            ->where('andrew_forced',1);

        if($username)
        {
            $finder->where('IgnoredBy.username',$username);
        }
        if($ignoring)
        {
            $finder->where('IgnoredUser.username',$ignoring);
        }
        if($givenBy)
        {
            $finder->where('ForcedBy.username',$givenBy);
        }

        switch ($sortby) {
            case 'username':
                $finder->order('IgnoredBy.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'ignoring':
                $finder->order('IgnoredUser.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'given_by':
                $finder->order('ForcedBy.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'date':
            default:
                $finder->order('andrew_forced_datetime', $order == 'asc' ? 'ASC' : 'DESC');
                break;
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
            'userFilter' => $username,
            'ignoringFilter' => $ignoring,
            'givenByFilter' => $givenBy
        ];

        return $this->view('Andrew\ModeratorPanel\ForceIgnored:View', 'andrew_moderatorpanel_force_ignored_view',$viewParams);
    }
}
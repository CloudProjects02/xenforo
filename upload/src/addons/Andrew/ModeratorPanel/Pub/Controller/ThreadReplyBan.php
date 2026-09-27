<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class ThreadReplyBan extends AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewThreadBan())
        {
            return $this->noPermission();
        }

        $filterInput = $this->filter([
            'username' => 'str',
            'banned_by' => 'str',
            'order' => 'str',
            'sortby' => 'str'
        ]);

        $username = $filterInput['username'] ?? null;
        $bannedBy = $filterInput['banned_by'] ?? null;
        $order = $filterInput['order'] ?? 'desc';
        $sortby = $filterInput['sortby'] ?? 'date';

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:ThreadReplyBan')
            ->with('User')
            ->with('Thread');

        if ($username)
        {
            $finder->where('User.username', $username);
        }

        if ($bannedBy)
        {
            $finder->where('BannedBy.username', $bannedBy);
        }

        switch ($sortby) {
            case 'username':
                $finder->order('User.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'banned_by':
                $finder->order('BannedBy.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'ban_end':
                $finder->order('end_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'ban_started':
            default:
                $finder->order('ban_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
        }

        $total = $finder->total();
        $finder->limitByPage($page, $perPage);

        $bannedUsers = $finder->fetch();

        $viewParams = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'users' => $bannedUsers,
            'filters' => $filterInput,
            'userFilter' => $username,
            'bannedByFilter' => $bannedBy
        ];

        return $this->view('Andrew\ModeratorPanel\ThreadBanList:View', 'andrew_moderatorpanel_threadbanlist_view',$viewParams);
    }
}
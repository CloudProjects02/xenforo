<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class BannedUser extends AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewBannedUsersListMP())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $showSpamUsers = $this->options()->andrewModeratorPanelShowSpamBan;

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

        $finder = \XF::finder('XF:UserBan')
            ->with('User', true);

        if(!$showSpamUsers)
        {

            $spamCleanRepo = $this->repository('XF:Spam');
            $spamCleanFinder = $spamCleanRepo->findSpamCleanerLogsForList();

            // Fetch spam cleaner logs and get the user IDs
            $spamCleanerLogs = $spamCleanFinder->fetch();
            $spamCleanerUserIds = $spamCleanerLogs->pluckNamed('user_id');

            if (!empty($spamCleanerUserIds)) {
                $spamCleanerUserIdsString = implode(',', array_map('intval', $spamCleanerUserIds));
                // Manually build the FIND_IN_SET condition
                $inSetCondition = "FIND_IN_SET(xf_user_ban.user_id, '{$spamCleanerUserIdsString}') = 0";

                // Apply the condition
                $finder->whereSql($inSetCondition);
            }
        }

        if ($username)
        {
            $finder->where('User.username', $username);
        }

        if ($bannedBy)
        {
            $finder->where('BanUser.username', $bannedBy);
        }

        switch ($sortby) {
            case 'username':
                $finder->order('User.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'banned_by':
                $finder->order('BanUser.username', $order == 'asc' ? 'ASC' : 'DESC');
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
            'bannedusers' => $bannedUsers,
            'filters' => $filterInput,
            'userFilter' => $username,
            'bannedByFilter' => $bannedBy
        ];

        return $this->view('Andrew\ModeratorPanel\BannedUsers:View', 'andrew_moderatorpanel_bannedusers_view', $viewParams);
    }
}
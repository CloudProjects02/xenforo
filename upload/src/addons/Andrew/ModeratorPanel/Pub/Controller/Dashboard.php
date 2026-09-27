<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

class Dashboard extends \XF\Pub\Controller\AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel()) {
            return $this->noPermission();
        }

        $positionOne = $this->options()->andrewModeratorPanelDashboardFirstPosition;
        $positionTwo = $this->options()->andrewModeratorPanelDashboardSecondPosition;
        $positionThree = $this->options()->andrewModeratorPanelDashboardThirdPosition;
        $positionFour = $this->options()->andrewModeratorPanelDashboardFourthPosition;
        $positionFive = $this->options()->andrewModeratorPanelDashboardFifthPosition;
        $positionSix = $this->options()->andrewModeratorPanelDashboardSixthPosition;

        $counts = null;
        $reportCount = null;
        if (in_array(1, [$positionOne, $positionTwo, $positionThree, $positionFour, $positionFive, $positionSix]))
        {
            $reportCount = $this->getReportCount();
            $counts = $this->repository('XF:SessionActivity')->getOnlineCounts();
        }

        $stats = null;
        if (in_array(2, [$positionOne, $positionTwo, $positionThree, $positionFour, $positionFive, $positionSix]))
        {
            $stats = $this->getDashboardStats();
        }

        $bannedUsers = null;
        if (in_array(3, [$positionOne, $positionTwo, $positionThree, $positionFour, $positionFive, $positionSix]))
        {
            $bannedUsers = $this->getRecentBannedUsers();
        }

        $warnings = null;
        if (in_array(4, [$positionOne, $positionTwo, $positionThree, $positionFour, $positionFive, $positionSix]))
        {
            $warnings = $this->getRecentWarnings();
        }

        $userNotes = null;
        if (in_array(5, [$positionOne, $positionTwo, $positionThree, $positionFour, $positionFive, $positionSix]))
        {
            $userNotes = $this->getRecentUserNotes();
        }

        $recentRegistered = null;
        if (in_array(5, [$positionOne, $positionTwo, $positionThree, $positionFour, $positionFive, $positionSix]))
        {
            $recentRegistered = $this->getRecentRegistered();
        }

        $viewParams = [
            'stats' => $stats,
            'bannedusers' => $bannedUsers,
            'warnings' => $warnings,
            'counts' => $counts,
            'user_notes' => $userNotes,
            'recent_registered' => $recentRegistered,
            'reportCount' => $reportCount,
            'positionOne' => $positionOne,
            'positionTwo' => $positionTwo,
            'positionThree' => $positionThree,
            'positionFour' => $positionFour,
            'positionFive' => $positionFive,
            'positionSix' => $positionSix
        ];

        return $this->view('Andrew\ModeratorPanel:View', 'andrew_moderatorpanel_view', $viewParams);
    }

    protected function getDashboardStats()
    {
        $stats = [];

        /** @var \XF\Stats\Grouper\AbstractGrouper $grouper */
        $grouper = $this->app->create('stats.grouper', 'daily');

        foreach ($this->getDashboardStatGraphs() as $statDisplayTypes) {
            $now = \XF::$time;
            $start = $now - 30 * 86400;
            $end = $now - ($now % 86400) - 1; // yesterday

            /** @var \XF\Service\Stats\Grapher $grapher */
            $grapher = $this->service('XF:Stats\Grapher', $start, $end, $statDisplayTypes);
            $stats[] = [
                'data' => $grapher->getGroupedData($grouper),
                'phrases' => $this->repository('XF:Stats')->getStatsTypePhrases($statDisplayTypes)
            ];
        }

        return $stats;
    }

    protected function getReportCount()
    {
        return $this->repository('XF:Report')
            ->findReports(['resolved', 'rejected'], time() - 86400)
            ->fetch()->filterViewable()
            ->count();
    }

    protected function getDashboardStatGraphs()
    {
        $options = $this->options();

        return [
            [$options->andrewModeratorPanelChartOneStatOne, $options->andrewModeratorPanelChartOneStatTwo],
            [$options->andrewModeratorPanelChartTwoStatOne, $options->andrewModeratorPanelChartTwoStatTwo],
        ];
    }

    protected function getRecentBannedUsers()
    {
        $showSpamUsers = $this->options()->andrewModeratorPanelShowSpamBan;

        $finder = \XF::finder('XF:UserBan')
            ->with('User', true)
            ->order('ban_date', 'DESC')
            ->limit(10);

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

        return $finder->fetch();
    }

    protected function getRecentWarnings()
    {
        return \XF::finder('XF:Warning')
            ->with('User', true)
            ->order('warning_date', 'DESC')
            ->limit(10)
            ->fetch();

    }
    protected function getRecentRegistered()
    {
        return \XF::finder('XF:User')
            ->order('register_date', 'DESC')
            ->limit(10)
            ->fetch();
    }

    protected function getRecentUserNotes()
    {
        return $this->repository('Andrew\ModeratorPanel:UserNote')
            ->findUserNote()
            ->with('User')->fetch(5);
    }
}
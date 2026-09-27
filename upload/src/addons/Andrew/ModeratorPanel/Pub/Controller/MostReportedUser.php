<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class MostReportedUser extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewReportedUsers()) {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $offset = ($page - 1) * $perPage;
        $showBannedUsers = $this->options()->andrewModeratorPanelMostReportedBannedUsers;

        $filterInput = $this->filter([
            'username' => 'str'
        ]);

        $username = $filterInput['username'] ?? null;

        $db = \XF::db();

        $whereConditions = [];
        $queryParams = [];
        if ($showBannedUsers == 0) {
            $whereConditions[] = 'xf_user.is_banned != 1';
        }
        if ($username) {
            $whereConditions[] = 'xf_user.username LIKE ?';
            $queryParams[] = '%' . $username . '%';
        }

        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $valuesQuery = "
            SELECT xf_user.user_id, xf_user.username, xf_user.message_count, 
                   xf_user.warning_points, 
                   COUNT(xf_report.report_id) AS report_count
            FROM xf_user
            INNER JOIN xf_report ON xf_user.user_id = xf_report.content_user_id
            {$whereClause}
            GROUP BY xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points
            HAVING COUNT(xf_report.report_id) > 0
            ORDER BY report_count DESC
            LIMIT ?, ?
        ";

        $totalQuery = "
            SELECT xf_user.user_id, xf_user.username, xf_user.message_count, 
                   xf_user.warning_points, 
                   COUNT(xf_report.report_id) AS report_count
            FROM xf_user
            INNER JOIN xf_report ON xf_user.user_id = xf_report.content_user_id
            {$whereClause}
            GROUP BY xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points
            HAVING COUNT(xf_report.report_id) > 0
            ORDER BY report_count DESC
        ";

        // Adding offset and limit parameters at the end of the query parameters array
        $queryParamsForValues = $queryParams;
        $queryParamsForValues[] = $offset;
        $queryParamsForValues[] = $perPage;

        // Execute the queries
        $values = $db->fetchAllKeyed($valuesQuery, 'user_id', $queryParamsForValues);
        $total = $db->fetchAll($totalQuery, $queryParams);

        $users = \XF::em()->findByIds('XF:User', array_keys($values));
        $users = $users->sortByList(array_keys($values));
        $total = count($total);

        $viewParams = [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'users' => $users,
            'values' => $values,
            'filters' => $filterInput,
            'userFilter' => $username ? \XF::finder('XF:User')->where('username', $username)->fetchOne() : null,
        ];

        return $this->view('Andrew\ModeratorPanel:MostReportedUsers', 'andrew_moderatorpanel_mostreportedusers_view', $viewParams);
    }
}
<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class MostWarnedUser extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewWarnedUsers()) {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;
        $offset = ($page - 1) * $perPage;
        $showBannedUsers = $this->options()->andrewModeratorPanelMostWarnedBannedUsers;

        $filterInput = $this->filter([
            'user' => 'str'
        ]);

        $username = $filterInput['user'] ?? null;

        $db = \XF::db();

        $whereConditions = [];
        $queryParams = [];
        if ($showBannedUsers == 0) {
            $whereConditions[] = 'xf_user.is_banned != 1';
        }
        if ($username) {
            $whereConditions[] = 'xf_user.username = ?';
            $queryParams[] = $username;
        }

        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $valuesQuery = "
            SELECT xf_user.user_id, xf_user.username, xf_user.message_count, 
                   COUNT(xf_warning.warning_id) AS warning_count, 
                   xf_user.warning_points AS active_points, 
                   SUM(xf_warning.points) AS total_points
            FROM xf_user
            INNER JOIN xf_warning ON xf_user.user_id = xf_warning.user_id
            {$whereClause}
            GROUP BY xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points
            HAVING COUNT(xf_warning.warning_id) > 0
            ORDER BY warning_count DESC
            LIMIT ?, ?
        ";

        $totalQuery = "
            SELECT xf_user.user_id, xf_user.username, xf_user.message_count, 
                   COUNT(xf_warning.warning_id) AS warning_count, 
                   xf_user.warning_points AS active_points, 
                   SUM(xf_warning.points) AS total_points
            FROM xf_user
            INNER JOIN xf_warning ON xf_user.user_id = xf_warning.user_id
            {$whereClause}
            GROUP BY xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points
            HAVING COUNT(xf_warning.warning_id) > 0
            ORDER BY warning_count DESC
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

        return $this->view('Andrew\ModeratorPanel:MostWarnedUsers', 'andrew_moderatorpanel_mostwarnedusers_view', $viewParams);
    }
}
<?php

namespace Andrew\ModeratorPanel\Pub\Controller;
use Andrew\ModeratorPanel\Entity\UserNoteCategory;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\IpRepository;
use XF\Util\Ip;

class User extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        if (isset($params['user_id']))
        {
            return $this->rerouteController(__CLASS__, 'view', $params);
        }
    }

    public function actionView(ParameterBag $params)
    {
        $userId = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel()) {
            return $this->noPermission();
        }

        // Fetch the user along with their ban information
        $user = \XF::finder('XF:User')
            ->with('Ban')
            ->where('user_id', $userId)
            ->fetchOne();

        if (!$user) {
            throw $this->exception($this->notFound(\XF::phrase('andrew_moderatorpanel_user_not_found')));
        }

        $page = $this->filterPage();
        $perPage = 40;

        // Fetch user warnings
        $warnings = \XF::finder('XF:Warning')
            ->with('User')
            ->where('user_id', $userId)
            ->order('warning_date', 'DESC')
            ->limitByPage($page, $perPage)
            ->fetch();

        // Fetch user warning points and report count
        $userRepo = $this->repository('XF:User');
        $warningPoints = $userRepo->warningPoints($user->user_id);
        $reportCount = $userRepo->reportCount($user->user_id);

        // Fetch users ignoring and ignored by the specified user
        $ignored = \XF::finder('XF:UserIgnored')
            ->where('ignored_user_id', $userId)
            ->fetch();

        $ignoring = \XF::finder('XF:UserIgnored')
            ->where('user_id', $userId)
            ->fetch();

        // Fetch thread reply bans
        $threadBans = \XF::finder('XF:ThreadReplyBan')
            ->with('User')
            ->with('Thread')
            ->where('user_id', $userId)
            ->order('ban_date', 'DESC')
            ->limitByPage($page, $perPage)
            ->fetch();

        // Prepare view parameters
        $viewParams = [
            'user' => $user,
            'warnings' => $warnings,
            'warning_points' => $warningPoints,
            'report_count' => $reportCount,
            'ignored' => $ignored,
            'ignoring' => $ignoring,
            'thread_bans' => $threadBans
        ];

        // Return the view with the prepared parameters
        return $this->view('Andrew\ModeratorPanel:User\View', 'andrew_moderatorpanel_user_view', $viewParams);
    }


    public function actionCurrentBan(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewBannedUsersListMP())
        {
            return $this->noPermission();
        }

        $finder = \XF::finder('XF:User');
        $user = $finder
            ->with('Ban')
            ->where('user_id',$user_id)
            ->fetchOne();

        $viewParams = [
            'user' => $user
        ];

        return $this->view('Andrew\ModeratorPanel:User\CurrentBan', 'andrew_moderatorpanel_user_ban_list', $viewParams);
    }

    public function actionWarnings(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewWarnedUsers())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:Warning')->limitByPage($page, $perPage);
        $warnings = $finder
            ->with('User')
            ->where('user_id',$user_id)
            ->order('warning_date','DESC')
            ->fetch();

        $finder = \XF::finder('XF:User');
        $user = $finder
            ->where('user_id',$user_id)
            ->fetchOne();

        $viewParams = [
            'warnings' => $warnings,
            'user' => $user
        ];

        return $this->view('Andrew\ModeratorPanel:User\Warnings', 'andrew_moderatorpanel_user_warning_list', $viewParams);
    }

    public function actionReports(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:Report')->limitByPage($page, $perPage);
        $reports = $finder
            ->with('User')
            ->where('content_user_id',$user_id)
            ->order('first_report_date','DESC')
            ->fetch();

        $finder = \XF::finder('XF:User');
        $user = $finder
            ->where('user_id',$user_id)
            ->fetchOne();

        $viewParams = [
            'reports' => $reports,
            'user' => $user
        ];

        return $this->view('Andrew\ModeratorPanel:User\Reports', 'andrew_moderatorpanel_user_report_list', $viewParams);
    }

    public function actionThreadBans(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewThreadBan())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:ThreadReplyBan')->limitByPage($page, $perPage);
        $threadbans = $finder
            ->with('User')
            ->with('Thread')
            ->where('user_id',$user_id)
            ->order('ban_date','DESC')
            ->fetch();

        $viewParams = [
            'threadbans' => $threadbans
        ];

        return $this->view('Andrew\ModeratorPanel:User\ThreadBans', 'andrew_moderatorpanel_user_threadbans_list', $viewParams);
    }

    public function actionIgnoredBy(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewIgnoredUsers())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:UserIgnored')->limitByPage($page, $perPage);
        $ignores = $finder
            ->with('IgnoredBy',$user_id)
            ->where('ignored_user_id',$user_id)
            ->fetch();

        $ignore_count = count($ignores);

        $viewParams = [
            'ignores' => $ignores,
            'ignore_count' => $ignore_count,
            'perPage'
        ];

        return $this->view('Andrew\ModeratorPanel:User\IgnoredBy', 'andrew_moderatorpanel_user_ignores_list', $viewParams);
    }

    public function actionIgnoring(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewIgnoredUsers())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $finder = \XF::finder('XF:UserIgnored')->limitByPage($page, $perPage);
        $ignores = $finder
            ->where('user_id',$user_id)
            ->fetch();

        $ignore_count = count($ignores);

        $viewParams = [
            'ignores' => $ignores,
            'ignore_count' => $ignore_count,
            'perPage'
        ];

        return $this->view('Andrew\ModeratorPanel:User\Ignoring', 'andrew_moderatorpanel_user_ignoring_list', $viewParams);
    }

    public function actionIpAddresses(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewIps())
        {
            return $this->noPermission();
        }

        $finder = \XF::finder('XF:User');
        $user = $finder
            ->where('user_id',$user_id)
            ->fetchOne();

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');

        $ips = $ipRepo->getIpsByUser($user);

        $viewParams = [
            'user' => $user,
            'ips' => $ips
        ];
        return $this->view('Andrew\ModeratorPanel:User\IpAddresses', 'andrew_moderatorpanel_user_ip_list', $viewParams);
    }

    public function actionUserNotes(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewUserNotes())
        {
            return $this->noPermission();
        }

        $repo = $this->repository('Andrew\ModeratorPanel:UserNote');
        $finder = $repo->findUserNote();

        $page = $this->filterPage();
        $perPage = 20;

        $userNotesQuery = $finder->with('User')
            ->where('note_user_id', $user_id);

        if (!$visitor->canViewPrivilegedUserNotes()) {
            $userNotesQuery->where('is_privileged', false);
        }

        $total = $userNotesQuery->total();

        $userNotesQuery->limitByPage($page, $perPage);
        $user_notes = $userNotesQuery->fetch();


        $categoryRepo = $this->repository('Andrew\ModeratorPanel:UserNoteCategory');
        $categoryFinder = $categoryRepo->fetchCategoryList();
        $categories = $categoryFinder->fetch();

        $categories = $categories->filter(function (UserNoteCategory $category) use ($visitor) {
            return $category->isUsableByUser($visitor);
        });

        $viewParams = [
            'user_notes' => $user_notes,
            'categoryList' => $categories,
            'user_id' => $user_id,
            'perPage' => $perPage,
            'total' => $total
        ];

        return $this->view('Andrew\ModeratorPanel:User\UserNotes', 'andrew_moderatorpanel_user_notes_list', $viewParams);
    }


    protected function fetchUserNotes($userId, $page, $perPage)
    {
        $repo = $this->repository('Andrew\ModeratorPanel:UserNote');
        $finder = $repo->findUserNote()->limitByPage($page, $perPage)->with('User');

        if ($userId) {
            $finder->where('note_user_id', $userId);
        }

        return $finder->fetch();
    }

    public function actionRecentLogins(ParameterBag $params)
    {
        $userId = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewRecentLoginsMP())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $finder = $this->finder('Andrew\ModeratorPanel:RecentLogin')->limitByPage($page, $perPage);
        $finder->where('user_id',$userId);

        $viewParams = [
            'entries' => $finder->fetch()
        ];
        return $this->view('Andrew\ModeratorPanel:User\RecentLogins', 'andrew_moderatorpanel_recent_login_list', $viewParams);

    }

    public function actionChangeLog(ParameterBag $params)
    {
        $user_id = $params->user_id;
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewChangeLog())
        {
            return $this->noPermission();
        }

        $finder = \XF::finder('XF:User');
        $user = $finder
            ->where('user_id',$user_id)
            ->fetchOne();

        $page = $this->filterPage();
        $perPage = 20;

        $repo = $this->repository('XF:ChangeLog');
        $finder = $repo->findChangeLogsByContent('user', $user->user_id)->limitByPage($page, $perPage);

        $changes = $finder->fetch();
        $repo->addDataToLogs($changes);

        $viewParams = [
            'user' => $user,
            'changesGrouped' => $repo->groupChangeLogs($changes),
        ];

        return $this->view('Andrew\ModeratorPanel:User\ChangeLog', 'andrew_moderatorpanel_user_change_log_profile', $viewParams);
    }

    public function actionSearch()
    {
        $visitor = \XF::visitor();
        if (!$visitor->canViewModeratorPanel() || !$visitor->canSearchUsers())
        {
            return $this->noPermission();
        }

        $this->setSectionContext('searchForUsers');

        $lastUserId = $this->filter('last_user_id', 'uint');
        $lastUser = $lastUserId ? $this->em()->find('XF:User', $lastUserId) : null;

        $viewParams = $this->getSearcherParams($lastUser ? ['lastUser' => $lastUser] : []);

        return $this->view('Andrew\ModeratorPanel:User\Search', 'andrew_moderatorpanel_user_search', $viewParams);
    }

    protected function getSearcherParams(array $extraParams = [])
    {
        $visitor = \XF::visitor();
        if (!$visitor->canViewModeratorPanel())
        {
            return $this->noPermission();
        }

        $searcher = $this->searcher('XF:User');

        $viewParams = [
            'criteria' => $searcher->getFormCriteria(),
            'sortOrders' => $searcher->getOrderOptions()
        ];
        return $viewParams + $searcher->getFormData() + $extraParams;
    }

    public function actionIpUsers()
    {
        $visitor = \XF::visitor();
        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewIps()) {
            return $this->noPermission();
        }

        /** @var IpRepository $ipRepo */
        $ipRepo = $this->repository(IpRepository::class);

        $ip = $this->filter('ip', 'str');
        $parsed = Ip::parseIpRangeString($ip);

        if (!$parsed)
        {
            return $this->message(\XF::phrase('please_enter_valid_ip_or_ip_range'));
        }
        else if ($parsed['isRange'])
        {
            $ips = $ipRepo->getUsersByIpRange($parsed['startRange'], $parsed['endRange']);
        }
        else
        {
            $ips = $ipRepo->getUsersByIp(
                Ip::binaryToString($parsed['startRange'])
            );
        }

        if ($ips) {
            $viewParams = [
                'ip' => $ip,
                'ipParsed' => $parsed,
                'ipPrintable' => $parsed['printable'],
                'ips' => $ips
            ];
            return $this->view('Andrew\ModeratorPanel:User\IpUsers', 'andrew_moderatorpanel_ip_users_list', $viewParams);
        } else {
            return $this->message(\XF::phrase('no_users_logged_at_ip'));
        }
    }


    public function actionList()
    {
        $visitor = \XF::visitor();
        if (!$visitor->canViewModeratorPanel())
        {
            return $this->noPermission();
        }

        $criteria = $this->filter('criteria', 'array');
        $order = $this->filter('order', 'str');
        $direction = $this->filter('direction', 'str');

        $page = $this->filterPage();
        $perPage = 40;

        $searcher = $this->searcher('XF:User', $criteria);

        $finder = $searcher->getFinder();
        $finder->limitByPage($page, $perPage);

        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);
        if (strlen($filter['text']))
        {
            $finder->where('username', 'LIKE', $finder->escapeLike($filter['text'], $filter['prefix'] ? '?%' : '%?%'));
        }

        $total = $finder->total();
        $users = $finder->fetch();

        $this->assertValidPage($page, $perPage, $total, 'moderatorpanel/user/list');

        if (!strlen($filter['text']) && $total == 1 && ($user = $users->first()))
        {
            return $this->redirect($this->buildLink('moderatorpanel/user', $user));
        }

        $viewParams = [
            'users' => $users,
            'criteria' => $criteria,
            'order' => $order,
            'direction' => $direction,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,

        ];
        return $this->view('Andrew\ModeratorPanel:User\List', 'andrew_moderatorpanel_user_list', $viewParams);
    }
}
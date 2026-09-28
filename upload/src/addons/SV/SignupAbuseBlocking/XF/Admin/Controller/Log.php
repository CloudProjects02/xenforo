<?php /** @noinspection PhpUnusedParameterInspection */

namespace SV\SignupAbuseBlocking\XF\Admin\Controller;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog;
use SV\SignupAbuseBlocking\Finder\UserLoginLog as UserLoginLogFinder;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use SV\SignupAbuseBlocking\Finder\UserRegistrationLog as UserRegistrationLogFinder;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use XF\Util\Arr as ArrUtil;
use XF\Util\Ip;
use function count;
use function strlen;

/**
 * Class Log
 *
 * @package SV\SignupAbuseBlocking\XF\Admin\Controller
 */
class Log extends XFCP_Log
{
    protected function getUserRegistrationFilterInputForSv(string &$usernames = null) : array
    {
        $filters = [];
        $usernames = '';

        $em = $this->em();
        $input = $this->filter([
            'details' => 'str',

            'user_ids' => 'array-uint',
            'usernames' => 'str',

            'log_date_start' => '?datetime',
            'log_date_end' => '?datetime'
        ]);

        if (strlen($input['details']) !== 0)
        {
            $filters['details'] = $input['details'];
        }

        if ($input['log_date_start'])
        {
            $filters['log_date_start'] = $input['log_date_start'];
        }

        if ($input['log_date_end'])
        {
            $filters['log_date_end'] = $input['log_date_end'];
        }

        $users = null;
        if ($input['user_ids'])
        {
            $users = Helper::find(UserEntity::class, $input['user_ids']);
        }
        else if ($input['usernames'])
        {
            $users = Helper::repository(\XF\Repository\User::class)->getUsersByNames(ArrUtil::stringToArray($input['usernames'], '/,\s*/'));
        }

        if ($users instanceof UserEntity)
        {
            $users = $em->getBasicCollection([
                $users->getEntityId() => $users
            ]);
        }

        if ($users instanceof AbstractCollection && $users->count())
        {
            $filters['user_ids'] = [];
            $usernamesArr = [];

            /** @var UserEntity $user */
            foreach ($users AS $user)
            {
                $filters['user_ids'][] = $user->user_id;
                $usernamesArr[] = $user->username;
            }

            $usernames = \implode(',', $usernamesArr);
        }

        return $filters;
    }

    protected function applyUserRegistrationFilterForSv(UserRegistrationLogFinder $logFinder, array $filters)
    {
        if (!\count($filters))
        {
            return;
        }

        if (!empty($filters['user_ids']))
        {
            $logFinder->where('user_id', $filters['user_ids']);
        }

        if (\array_key_exists('details', $filters) && strlen($filters['details']) !== 0)
        {
            $logFinder->where(
                'details',
                'LIKE',
                $logFinder->escapeLike($filters['details'], '%?%')
            );
        }

        if (!empty($filters['log_date_start']))
        {
            $logFinder->where('log_date', '>=', $filters['log_date_start']);
        }

        if (!empty($filters['log_date_end']))
        {
            $logFinder->where('log_date', '<=', $filters['log_date_end']);
        }
    }

    public function actionUserRegistration(ParameterBag $parameterBag): AbstractReply
    {
        if ($parameterBag->user_registration_log_id ?? 0)
        {
            return $this->rerouteController(__CLASS__, 'userRegistrationView', $parameterBag);
        }

        $linkParams = $this->getUserRegistrationFilterInputForSv($usernames);
        if ($this->isPost() && $this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('logs/user-registration', null, $linkParams));
        }

        $page = $this->filterPage();
        $perPage = 20;

        $logFinder = UserRegistrationLogRepo::get()
            ->findUserRegistrationLogsForList()
            ->limitByPage($page, $perPage);
        $this->applyUserRegistrationFilterForSv($logFinder, $linkParams);

        $logs = $logFinder->fetch();
        /** @var UserRegistrationLog $log */
        foreach ($logs as $log)
        {
            $log->setOption('linkUsernameInDetails', false);
        }
        $viewParams = [
            'entries' => $logs,

            'usernames'   => $usernames,

            'pageLink'   => 'logs/user-registration',

            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $logFinder->total(),

            'linkParams' => $linkParams,
        ];

        return $this->view('SV\SignupAbuseBlocking\XF:Log\UserRegistration\Listing', 'svSignupAbuseBlocking_log_user_registration_list', $viewParams);
    }

    public function actionUserRegistrationView(ParameterBag $parameterBag): AbstractReply
    {
        $entry = $this->assertUserRegistrationLogExists($parameterBag->user_registration_log_id ?? 0);

        // stop xdebug overloading the dump...
        @ini_set('xdebug.overload_var_dump', 'off');

        $userAgent = $entry->UserAgent;
        if ($userAgent !== null)
        {
            $entry->request_state = array_merge(['user_agent' => $userAgent->user_agent], $entry->request_state);
            $entry->setReadOnly(true);
        }

        $viewParams = [
            'entry' => $entry
        ];
        return $this->view(
            'SV\SignupAbuseBlocking\XF:Log\UserRegistration\View',
            'svSignupAbuseBlocking_log_user_registration_view',
            $viewParams
        );
    }

    protected function getUserLoginsFilterInputForSv(string &$usernames = null) : array
    {
        $filters = [];
        $usernames = '';

        $em = $this->em();
        $input = $this->filter([
            'action' => 'str',
            'ip_address' => 'str',
            'user_agent' => 'str',
            'asn' => 'uint',
            'country' => 'str',

            'user_ids' => 'array-uint',
            'usernames' => 'str',

            'log_date_start' => '?datetime',
            'log_date_end' => '?datetime'
        ]);

        if ($input['action'])
        {
            $filters['action'] = $input['action'];
        }

        if ($input['ip_address'])
        {
            $filters['ip_address'] = $input['ip_address'];
        }

        if ($input['user_agent'])
        {
            $filters['user_agent'] = $input['user_agent'];
        }

        if ($input['asn'])
        {
            $filters['asn'] = $input['asn'];
        }

        if ($input['country'])
        {
            $filters['country'] = $input['country'];
        }

        if ($input['log_date_start'])
        {
            $filters['log_date_start'] = $input['log_date_start'];
        }

        if ($input['log_date_end'])
        {
            $filters['log_date_end'] = $input['log_date_end'];
        }

        $users = null;
        if ($input['user_ids'])
        {
            $users = Helper::find(UserEntity::class, $input['user_ids']);
        }
        else if ($input['usernames'])
        {
            $users = Helper::repository(\XF\Repository\User::class)->getUsersByNames(ArrUtil::stringToArray($input['usernames'], '/,\s*/'));
        }

        if ($users instanceof UserEntity)
        {
            $users = $em->getBasicCollection([
                $users->getEntityId() => $users
            ]);
        }

        if ($users instanceof AbstractCollection && $users->count())
        {
            $filters['user_ids'] = [];
            $usernamesArr = [];

            /** @var UserEntity $user */
            foreach ($users AS $user)
            {
                $filters['user_ids'][] = $user->user_id;
                $usernamesArr[] = $user->username;
            }

            $usernames = \implode(',', $usernamesArr);
        }

        return $filters;
    }

    protected function applyUserLoginsFilterForSv(UserLoginLogFinder $userLoginFinder, array $filters)
    {
        if (count($filters) === 0)
        {
            return;
        }

        if (!empty($filters['action']))
        {
            $userLoginFinder->where('action', $filters['action']);
        }

        if (!empty($filters['ip_address']))
        {
            $results = Ip::parseIpRangeString($filters['ip_address']);
            if (is_array($results))
            {
                $userLoginFinder->where('ip_address', '>=', $results['startRange']);
                $userLoginFinder->where('ip_address', '<=', $results['endRange']);
            }
            else
            {
                $userLoginFinder->whereImpossible();
            }
        }

        if (!empty($filters['asn']))
        {
            $userLoginFinder->where('asn', $filters['asn']);
        }

        if (!empty($filters['country']))
        {
            $userLoginFinder->where('country', 'like', $userLoginFinder->escapeLike($filters['country'], '%?%'));
        }

        if (!empty($filters['user_agent']))
        {
            $userLoginFinder->where('UserAgent.user_agent', 'like', $userLoginFinder->escapeLike($filters['user_agent'], '%?%'));
        }

        if (!empty($filters['user_ids']))
        {
            $userLoginFinder->where('user_id', $filters['user_ids']);
        }

        if (!empty($filters['log_date_start']))
        {
            $userLoginFinder->where('log_date', '>=', $filters['log_date_start']);
        }

        if (!empty($filters['log_date_end']))
        {
            $userLoginFinder->where('log_date', '<=', $filters['log_date_end']);
        }
    }

    protected function getLoginTypesForUserLogin(): array
    {
        $types = \XF::db()->fetchAllColumn('SELECT DISTINCT `action` FROM xf_sv_login_log');

        $language = \XF::app()->language();

        $kvp = [];
        foreach ($types as $type)
        {
            $phraseKey = 'svSignupAbuseBlocking_login_type.'. $type;
            if ($language->getPhraseText($phraseKey) !== false)
            {
                $kvp[$type] = \XF::phrase($phraseKey);
            }
            else
            {
                $kvp[$type] = $type;
            }
        }

        return $kvp;
    }

    public function actionSvUserLogins(ParameterBag $parameterBag): AbstractReply
    {
        $this->setSectionContext('svUserLoginLogs');

        $linkParams = $this->getUserLoginsFilterInputForSv($usernames);
        if ($this->isPost() && $this->filter('apply', 'bool'))
        {
            return $this->redirect($this->buildLink('logs/sv-user-logins', null, $linkParams));
        }

        $page = $this->filterPage();
        $perPage = 40;

        $userLoginFinder = UserLoginLogFinder::finder()
                                             ->with('User')
                                             ->setDefaultOrder('log_date', 'DESC');
        $this->applyUserLoginsFilterForSv($userLoginFinder, $linkParams);

        $total = $userLoginFinder->total();
        $logins = $userLoginFinder->limitByPage($page, $perPage)->fetch();
        $viewParams = [
            'entries' => $logins,
            'usernames' => $usernames,

            'loginTypes' => $this->getLoginTypesForUserLogin(),
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,

            'linkParams' => $linkParams,
        ];

        return $this->view('SV\SignupAbuseBlocking\XF:Log\UserRegistration\Listing', 'svSignupAbuseBlocking_login_list', $viewParams);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function actionSpamTriggerView(ParameterBag $params)
    {
        // stop xdebug overloading the dump...
        @ini_set('xdebug.overload_var_dump', 'off');
        return parent::actionSpamTriggerView($params);
    }

    protected function assertUserRegistrationLogExists(int $id, array $with = [], string $phraseKey = 'requested_log_entry_not_found'): UserRegistrationLog
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->assertRecordExists('SV\SignupAbuseBlocking:UserRegistrationLog', $id, $with, $phraseKey);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

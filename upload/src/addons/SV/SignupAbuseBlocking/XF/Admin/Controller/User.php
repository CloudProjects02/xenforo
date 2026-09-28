<?php

namespace SV\SignupAbuseBlocking\XF\Admin\Controller;

use SV\SignupAbuseBlocking\Admin\Controller\MultiAccount;
use SV\SignupAbuseBlocking\Finder\UserLoginLog as UserLoginLogFinder;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * Class User
 *
 * @package SV\SignupAbuseBlocking\XF\Admin\Controller
 */
class User extends XFCP_User
{
    public function actionRegistrationLog(ParameterBag $params): AbstractReply
    {
        $user = $this->assertUserExists($params->user_id ?? 0);
        $finder = UserRegistrationLogRepo::get()
                                         ->findUserRegistrationLogsForList()
                                         ->forUser($user);

        $total = $finder->total();
        $page = $this->filterPage();
        $perPage = 40;

        $logs = $finder->limit($perPage, ($page - 1) * $perPage)->fetch();
        foreach ($logs as $log)
        {
            $log->setOption('linkUsernameInDetails', false);
        }

        $viewParams = [
            'user' => $user,

            'hideFilters' => true,
            'entries'     => $logs,

            'pageLink' => 'users/registration-log',
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,

            'linkParams' => [],
        ];

        return $this->view('SV\SignupAbuseBlocking\XF:User\RegistrationLog', 'svSignupAbuseBlocking_log_user_registration_list', $viewParams);
    }

    public function actionSvLogins(ParameterBag $params): AbstractReply
    {
        $user = $this->assertUserExists($params->user_id ?? 0);
        $finder = UserLoginLogFinder::finder()
                                    ->byUser($user)
                                    ->setDefaultOrder('log_date', 'DESC');

        $total = $finder->total();
        $page = (int)$this->filterPage();
        $perPage = 40;

        $logins = $finder->limitByPage($page, $perPage)->fetch();

        $viewParams = [
            'user' => $user,

            'hideFilters' => true,
            'entries'     => $logins,

            'pageLink' => 'users/sv-logins',
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,

            'linkParams' => ['user_ids' => [$user->user_id]],
        ];

        return $this->view('SV\SignupAbuseBlocking\XF:User\SvLogins', 'svSignupAbuseBlocking_login_list', $viewParams);
    }

    public function actionMultipleAccounts(ParameterBag $params): AbstractReply
    {
        $userId = (int)$params->get('user_id');
        $this->assertUserExists($userId);

        $params = $params->params();
        $this->request()->set('user_id', $userId);

        return $this->rerouteController(MultiAccount::class, 'index', $params);
    }

    public function actionBatchUpdateAction()
    {
        if ($this->isPost())
        {
            if ($this->filter('actions.ban_expiry_type', 'str') === 'temporary')
            {
                // fetch from a datetime formatted input, and then store it back as an int
                $banExpiryDate = (int)$this->filter('actions.ban_end_date', 'datetime');
            }
            else
            {
                $banExpiryDate = 0;
            }
            $this->request->set('actions.ban_end_date', $banExpiryDate);
        }

        return parent::actionBatchUpdateAction();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

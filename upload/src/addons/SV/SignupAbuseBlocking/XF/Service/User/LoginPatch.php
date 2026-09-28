<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use XF\Entity\User as UserEntity;
use function time;

class LoginPatch extends XFCP_LoginPatch
{
    public function getSvTfaLoginUserId() : ?int
    {
        // copied from \XF\ControllerPlugin\Login::getTfaLoginUserId
        $session = \XF::session();

        $tfaLoginUserId = $session->get('tfaLoginUserId');
        $tfaLoginDate = $session->get('tfaLoginDate');

        if (!$tfaLoginUserId || \XF::visitor()->user_id)
        {
            return null;
        }

        if (!$tfaLoginDate || $tfaLoginDate < time() - 900)
        {
            return null;
        }

        return $tfaLoginUserId;
    }

    /**
     * @param string $password
     * @param null   $error
     * @return null|UserEntity
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function validate($password, &$error = null)
    {
        $user = parent::validate($password, $error);

        if ($user instanceof UserEntity && is_string($this->ip))
        {
            $action = Globals::$loginAction ?? 'validate_password';
            if ($action === 'login' && $this->getSvTfaLoginUserId())
            {
                $action = 'login_tfa';
            }

            $ip = $this->ip;
            $userAgent = (string)\XF::app()->request()->getUserAgent();
            UserRegistrationLogRepo::get()->logLogin($user, $ip, $userAgent, $action);
        }

        return $user;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\ControllerPlugin;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodUserSwitch;
use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\User as UserEntity;
use function property_exists;

class Login extends XFCP_Login
{
    /** @var bool */
    protected $doMultiAccountCheck = true;

    protected function clearCookieSkipList()
    {
        $cookies = parent::clearCookieSkipList();
        if ($cookieName = \XF::options()->svSockSignupAccountCookie)
        {
            $cookies[] = $cookieName;
        }

        return $cookies;
    }

    public function logoutVisitor()
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if ($visitor->user_id && !$this->skipMultiAccountCheck())
        {
            try
            {
                $multipleAccountRepo = MultipleAccountRepo::get();
                $receivedToken = $multipleAccountRepo->getCookieValue('logout');
                if (!$receivedToken)
                {
                    $multipleAccountRepo->setCookieValue($visitor->AccountDetectionToken);
                }
            }
            catch (\Throwable $e)
            {
                // do not block login if any sort of error occurs
                \XF::logException($e, true);
                if (\XF::$developmentMode)
                {
                    throw $e;
                }
            }
        }

        parent::logoutVisitor();
    }

    protected function skipMultiAccountCheck()
    {
        if (!(Globals::$armLoginPlugin ?? false))
        {
            return true;
        }

        // support https://xenforo.com/community/threads/login-as-user-lau2.153010/
        $controller = $this->controller;
        $app = \XF::app();
        if (
            property_exists($controller, 'lau_login') && $controller->lau_login
            || $app->offsetExists('lau_loginlogout')
            || $app->offsetExists('lau_id')
        )
        {
            return true;
        }

        return !$this->doMultiAccountCheck;
    }

    protected function isSvLoggableLogin(?string $action): bool
    {
        return $action === 'login_tfa_complete';
    }

    public function completeLogin(UserEntity $user, $remember)
    {
        if ($this->skipMultiAccountCheck())
        {
            Globals::$userBeforeLogin = null;
            parent::completeLogin($user, $remember);

            return;
        }

        $oldUser = Globals::$userBeforeLogin ?? null;
        if ($oldUser !== null)
        {
            $oldUser = \XF::visitor();
            if ($user->user_id !== $oldUser->user_id)
            {
                Globals::$userBeforeLogin = $oldUser;
                Globals::$userBeforeLoginType = new DetectionMethodUserSwitch();
            }
        }

        parent::completeLogin($user, $remember);

        $action = Globals::$loginAction ?? null;
        if ($this->isSvLoggableLogin($action))
        {
            $ip = \XF::app()->request()->getIp();
            $userAgent = (string)\XF::app()->request()->getUserAgent();
            UserRegistrationLogRepo::get()->logLogin($user, $ip, $userAgent, $action);
        }

        MultipleAccountRepo::get()->postLoginMultiAccountCheck($user);
    }

    public function actionKeepAlive()
    {
        $response =  parent::actionKeepAlive();

        try
        {
            /** @var ExtendedUserEntity $visitor */
            $visitor = \XF::visitor();
            if (!$visitor->user_id)
            {
                return $response;
            }

            if ($this->skipMultiAccountCheck())
            {
                return $response;
            }

            $multipleAccountRepo = MultipleAccountRepo::get();
            $receivedToken = $multipleAccountRepo->getCookieValue('login');

            if (!$receivedToken)
            {
                // no cookie, set it
                $multipleAccountRepo->setCookieValue($visitor->AccountDetectionToken);
            }
        }
        catch (\Throwable $e)
        {
            // do not block login if any sort of error occurs
            \XF::logException($e, true);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
        }

        return $response;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

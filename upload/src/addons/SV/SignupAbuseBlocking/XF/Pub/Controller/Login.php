<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodApiToken;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodEmailLink;
use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use XF\Mvc\ParameterBag;

/**
 * @Extends \XF\Pub\Controller\Login
 */
class Login extends XFCP_Login
{
    /** @var null|string */
    protected $actionL = null;

    public function preDispatch($action, ParameterBag $params)
    {
        $this->actionL = \strtolower($action);
        parent::preDispatch($action, $params);
    }

    protected function preDispatchController($action, ParameterBag $params)
    {
        Globals::$armLoginPlugin = true;
        parent::preDispatchController($action, $params);
    }

    public function assertNotBanned()
    {
        if ((\XF::options()->svAllowBannedLogout ?? false) && $this->actionL === 'logout')
        {
            return;
        }

        parent::assertNotBanned();
    }

    public function assertNotRejected($action)
    {
        if ((\XF::options()->svAllowRejectedLogout ?? false) && $this->actionL === 'logout')
        {
            return;
        }

        parent::assertNotRejected($action);
    }

    protected function svRegCookieJuggle()
    {
        $session = \XF::session();
        $cookie = $session->get('svRegCookie');
        if ($cookie)
        {
            MultipleAccountRepo::get()->setCookieValue($cookie);

            $session->remove('svRegCookie');
        }
    }

    public function actionTwoStep()
    {
        return Globals::withLoginAction('login_tfa_complete', function () { return parent::actionTwoStep(); } );
    }

    public function actionLogout(ParameterBag $params)
    {
        return $this->rerouteController('XF:Logout', 'index', $params);
    }

    public function actionRegister(ParameterBag $params)
    {
        $this->svRegCookieJuggle();
        Globals::$redirectedRegistration = true;
        return $this->rerouteController('XF:Register', 'index', $params);
    }

    public function actionRegisterRegister(ParameterBag $params)
    {
        $this->svRegCookieJuggle();
        Globals::$redirectedRegistration = true;
        return $this->rerouteController('XF:Register', 'register', $params);
    }

    public function actionRegisterConnectedAccounts(ParameterBag $params)
    {
        $this->svRegCookieJuggle();
        Globals::$redirectedRegistration = true;
        return $this->rerouteController('XF:Register', 'connectedAccount', $params);
    }

    public function actionRegisterConnectedAccountsAssociate(ParameterBag $params)
    {
        $this->svRegCookieJuggle();
        Globals::$redirectedRegistration = true;
        return $this->rerouteController('XF:Register', 'connectedAccountAssociate', $params);
    }

    public function actionRegisterConnectedAccountsRegister(ParameterBag $params)
    {
        $this->svRegCookieJuggle();
        Globals::$redirectedRegistration = true;
        return $this->rerouteController('XF:Register', 'connectedAccountRegister', $params);
    }

    public function actionApiToken()
    {
        // actionApiToken calls logoutVisitor => completeLogin
        $visitor = \XF::visitor();
        $userId = (int)$visitor->user_id;
        if ($userId !== 0)
        {
            // detect api-token is forcing a login when a user already is logged in
            Globals::$userBeforeLogin = $visitor;
            if (\XF::isAddOnActive('Kirby/FrictionlessLogin'))
            {
                Globals::$userBeforeLoginType = new DetectionMethodEmailLink();
            }
            else
            {
                Globals::$userBeforeLoginType = new DetectionMethodApiToken();
            }
        }
        try
        {
            return parent::actionApiToken();
        }
        finally
        {
            Globals::$userBeforeLogin = null;
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

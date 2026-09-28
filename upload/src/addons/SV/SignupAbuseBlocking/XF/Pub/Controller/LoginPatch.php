<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

use SV\SignupAbuseBlocking\Globals;
use XF\Mvc\ParameterBag;

if (\XF::$versionId >= 2030000)
{
    /**
     * XF2.3+
     * @Extends \XF\Pub\Controller\Login
     */
    class LoginPatch extends XFCP_LoginPatch
    {
        public function actionLogin(ParameterBag $params)
        {
            return Globals::withLoginAction('login', function () use ($params) { return parent::actionLogin($params); });
        }
    }
}
else
{
    /**
     * XF2.2
     * @Extends \XF\Pub\Controller\Login
     */
    class LoginPatch extends XFCP_LoginPatch
    {
        /**
         * @noinspection PhpSignatureMismatchDuringInheritanceInspection
         * @noinspection PhpParamsInspection
         */
        public function actionLogin()
        {
            return Globals::withLoginAction('login', function () { return parent::actionLogin(); });
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\XF\Admin\Controller;

use SV\SignupAbuseBlocking\Globals;

/**
 * @extends \XF\Admin\Controller\Login
 */
class Login extends XFCP_Login
{
    public function actionLogin()
    {
        return Globals::withLoginAction('login_admin', function () { return parent::actionLogin(); } );
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Admin\Controller;

use SV\SignupAbuseBlocking\Admin\Controller\AbstractAllowOrBan as ItemController;

/**
 * Class AllowEmailDomain
 *
 * @package SV\SignupAbuseBlocking\Admin\Controller
 */
class AllowEmailDomain extends ItemController
{
    protected function getRouteInfix(): string
    {
        return 'allowed-email-domains';
    }

    protected function getIdentifier(): string
    {
        return 'SV\SignupAbuseBlocking:AllowEmailDomain';
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

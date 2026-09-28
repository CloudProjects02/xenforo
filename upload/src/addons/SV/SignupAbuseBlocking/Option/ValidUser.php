<?php

namespace SV\SignupAbuseBlocking\Option;

use XF\Entity\Option as OptionEntity;

abstract class ValidUser
{
    public static function validUserId(int &$userId, OptionEntity $option): bool
    {
        if ($userId === 0)
        {
            $option->error(\XF::phrase('svSockSignupCheckReportingUser_invalid_user'));
            return false;
        }

        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

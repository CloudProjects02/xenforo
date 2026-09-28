<?php

namespace SV\SignupAbuseBlocking\Cron;

use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;

class Cleanup
{
    public static function hourly(): void
    {
        UserRegistrationLogRepo::get()->cleanUpSignupLog();
        UserRegistrationLogRepo::get()->cleanUpSignupThrottlingLog();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

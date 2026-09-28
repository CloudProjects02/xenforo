<?php

namespace SV\SignupAbuseBlocking\Option;

use SV\SignupAbuseBlocking\Repository\MaxMind as MaxMindRepo;
use XF\Entity\Option as OptionEntity;
use XF\Option\AbstractOption;

abstract class MaxMind extends AbstractOption
{
    public static function verifyOption(string &$value, OptionEntity $option): bool
    {
        if ($value === '' || $option->option_value === $value || (\XF::options()->svSignupAbuseBlockingMaxMindUpdate ?? true))
        {
            return true;
        }

        $repo = MaxMindRepo::get();
        $update1 = $repo->updateGeoIpDb($value);
        $update2 = $repo->updateAsnDb($value);

        return $update1 && $update2;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

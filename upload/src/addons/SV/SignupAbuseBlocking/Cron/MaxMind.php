<?php

namespace SV\SignupAbuseBlocking\Cron;

use SV\SignupAbuseBlocking\Repository\MaxMind as MaxMindRepo;

class MaxMind
{
    public static function GeoIP(): void
    {
        MaxMindRepo::get()->updateGeoIpDb();
    }

    public static function Asn(): void
    {
        MaxMindRepo::get()->updateAsnDb();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

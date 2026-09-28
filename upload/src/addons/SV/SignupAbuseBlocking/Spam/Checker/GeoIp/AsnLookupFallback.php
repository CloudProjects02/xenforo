<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\GeoIp;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Spam\GeoIpProvider;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;

class AsnLookupFallback extends GeoIpProvider
{
    public function isEnabled(): bool
    {
        return true;
    }

    public function resolveGeoIp(string $ip): ?string
    {
        $country = Globals::$asnCountryLookupFallback ?? null;
        if ($country === null)
        {
            /** @noinspection PhpUnusedLocalVariableInspection */
            [$asn, $country] = UserRegistrationLogRepo::get()->resolveIpToAsnAndCountry($ip, null, true, false);
        }

        return $country;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

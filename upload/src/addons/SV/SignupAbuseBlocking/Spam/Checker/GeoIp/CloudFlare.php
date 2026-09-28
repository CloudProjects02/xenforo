<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\GeoIp;

use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\SignupAbuseBlocking\Repository\CloudFlare as CloudFlareRepo;
use SV\SignupAbuseBlocking\Spam\GeoIpProvider;

class CloudFlare extends GeoIpProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->geoIpConfig['cloudflare']) && CloudFlareRepo::get()->canTrustCloudFlareHeaders();
    }

    public function resolveGeoIp(string $ip): ?string
    {
        $requestIp = $this->app->request()->getIp();
        if ($ip !== $requestIp)
        {
            $clampedIp = (string)(UserRegistrationLogRepo::get()->clampStringIpToMinimumCIDR($requestIp)[0] ?? '');
            if ($ip !== $clampedIp)
            {
                return null;
            }
        }

        return CloudFlareRepo::get()->getCountry();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function isNonInteractive(): bool
    {
        return false;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

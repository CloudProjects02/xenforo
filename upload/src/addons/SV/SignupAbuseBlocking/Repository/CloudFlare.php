<?php


namespace SV\SignupAbuseBlocking\Repository;

use SV\StandardLib\Helper;
use XF\Http\Request;
use XF\Mvc\Entity\Repository;
use SV\SignupAbuseBlocking\Util\Ip as Ip;

class CloudFlare extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function canTrustCloudFlareHeaders(): bool
    {
        $request = $this->app()->request();

        $ip = $request->getServer('REMOTE_ADDR');
        if ($ip === false)
        {
            return false;
        }
        $cfIp = $request->getServer('HTTP_CF_CONNECTING_IP');
        if ($cfIp === false)
        {
            return false;
        }

        if ($cfIp === $ip)
        {
            // the webserver has unwrapped the CF IP already, assert various CF headers exist as expected
            return $request->getServer('HTTP_CF_RAY') !== false && $request->getServer('HTTP_CF_VISITOR') !== false;
        }

        // web server isn't setup to unwrap cloudFlare proxying
        // Check to see if we are connecting from a known CloudFlare IP
        return Ip::ipMatchesRanges($ip, Request::$cloudFlareIps);
    }

    public function getCountry(): ?string
    {
        $countryCode = (string)$this->app()->request()->getServer('HTTP_CF_IPCOUNTRY', '');
        if ($countryCode === '')
        {
            return null;
        }

        return $countryCode;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

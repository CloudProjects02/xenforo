<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\GeoIp;

use SV\SignupAbuseBlocking\Spam\GeoIpProvider;
use function array_key_exists;
use function is_array;

class ipApi extends GeoIpProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->geoIpConfig['ipApi']);
    }

    public function resolveGeoIp(string $ip): ?string
    {
        $headers = [];
        if (empty($this->geoIpConfig['ipApiKey']))
        {
            /** @noinspection HttpUrlsUsage */
            $url = "http://ip-api.com/json/{$ip}";
        }
        else
        {
            $url = "https://pro.ip-api.com/json/{$ip}?key=" . \urlencode($this->geoIpConfig['ipApiKey']);
        }

        $response = $this->httpApiQuery($url, $headers);
        if (!is_array($response) || !array_key_exists('country_code', $response))
        {
            return null;
        }

        return $response['countryCode'] ?? 'XX';
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\GeoIp;

use SV\SignupAbuseBlocking\Spam\GeoIpProvider;
use function array_key_exists;
use function is_array;

class ipStack extends GeoIpProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->geoIpConfig['ipStack']) && !empty($this->geoIpConfig['ipStackKey']);
    }

    public function resolveGeoIp(string $ip): ?string
    {
        $url = "https://api.ipstack.com/{$ip}?output=json&fields=country_code";
        if (!empty($this->geoIpConfig['ipStackPaid']))
        {
            $url .= ',connection.asn,connection.isp';
        }
        $url .= '&access_key='.\urlencode($this->geoIpConfig['ipStackKey']);

        $response = $this->httpApiQuery($url);
        if (!is_array($response) || !array_key_exists('country_code', $response))
        {
            return null;
        }

        return $response['country_code'] ?? 'XX';
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

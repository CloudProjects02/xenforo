<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\GeoIp;

use SV\SignupAbuseBlocking\Spam\GeoIpProvider;
use function array_key_exists;
use function is_array;

class ipregistry extends GeoIpProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->geoIpConfig['ipregistry']) && !empty($this->geoIpConfig['ipregistryKey']);
    }

    public function resolveGeoIp(string $ip): ?string
    {
        $headers = [];
        $fields = 'fields=connection,location.country.code&';
        $url = "https://api.ipregistry.co/{$ip}?{$fields}key=" . \urlencode($this->geoIpConfig['ipregistryKey']);
        $response = $this->httpApiQuery($url, $headers);

        if (empty($response['location']['country']))
        {
            return null;
        }
        $country = $response['location']['country'];
        if (!is_array($country) || !array_key_exists('location', $country))
        {
            return null;
        }

        return $country['code'] ?? 'XX';
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

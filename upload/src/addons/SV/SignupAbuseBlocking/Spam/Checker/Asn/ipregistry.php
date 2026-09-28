<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Asn;

use SV\SignupAbuseBlocking\Spam\AsnProvider;
use function urlencode;

class ipregistry extends AsnProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->asnConfig['ipregistry']) && !empty($this->asnConfig['ipregistryKey']);
    }

    public function resolveIpToAsn(string $ip): ?array
    {
        $headers = [];
        $fields = 'fields=connection,location.country.code&';
        $url = "https://api.ipregistry.co/{$ip}?{$fields}key=" . urlencode($this->asnConfig['ipregistryKey']);
        $response = $this->httpApiQuery($url, $headers);

        $asn = null;
        $org = null;

        if (isset($response['connection']['asn']))
        {
            $asn = (int)$response['connection']['asn'];
        }

        if (!$org && isset($response['connection']['organization']))
        {
            $org = $response['connection']['organization'];
        }

        if (!$org && isset($response['connection']['domain']))
        {
            $org = $response['connection']['domain'];
        }

        if (!$asn || !$org)
        {
            return null;
        }

        return [$asn, $org, $response['location']['country']['code'] ?? null];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

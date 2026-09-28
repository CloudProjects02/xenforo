<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Asn;

use SV\SignupAbuseBlocking\Spam\AsnProvider;
use function preg_match;

class ipApi extends AsnProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->asnConfig['ipApi']) && !empty($this->asnConfig['ipApiKey']);
    }

    public function resolveIpToAsn(string $ip): ?array
    {
        $headers = [];
        $url = "https://pro.ip-api.com/json/{$ip}?key=" . \urlencode($this->asnConfig['ipApiKey']);

        $response = $this->httpApiQuery($url, $headers);
        if (empty($response['as']))
        {
            return null;
        }

        if (preg_match('#^as(\d+)\s+(.*)$#', $response['as'], $matches))
        {
            $asn = (int)$matches[1];
            $isp = $matches[2];
            if ($asn > 0)
            {
                return [$asn, $isp, $response['countryCode'] ?? null];
            }
        }

        return null;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

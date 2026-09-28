<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Asn;

use SV\SignupAbuseBlocking\Spam\AsnProvider;
use function urlencode;

class ipStack extends AsnProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->asnConfig['ipStack']) && !empty($this->asnConfig['ipStackKey']) && !empty($this->asnConfig['ipStackPaid']);
    }

    public function resolveIpToAsn(string $ip): ?array
    {
        $url = "https://api.ipstack.com/{$ip}?output=json&fields=country_code";
        if (!empty($this->asnConfig['ipStackPaid']))
        {
            $url .= ',connection.asn,connection.isp';
        }
        $url .= '&access_key='.urlencode($this->asnConfig['ipStackKey']);

        $response = $this->httpApiQuery($url);
        if (empty($response['connection']['asn']) || empty($response['connection']['isp']))
        {
            return null;
        }

        return [$response['connection']['asn'], $response['connection']['isp'], $response['country_code'] ?? null];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

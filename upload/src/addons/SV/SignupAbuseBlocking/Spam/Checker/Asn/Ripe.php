<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Asn;

use SV\SignupAbuseBlocking\Spam\AsnProvider;

class Ripe extends AsnProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->asnConfig['ripe']);
    }

    public function resolveIpToAsn(string $ip): ?array
    {
        $networkInfo = $this->httpApiQuery('https://stat.ripe.net/data/network-info/data.json?resource=' . $ip);
        if (empty($networkInfo['data']['asns'][0]))
        {
            return null;
        }
        $asn = (int)$networkInfo['data']['asns'][0];
        if ($asn === 0)
        {
            return null;
        }

        $asInfo = $this->httpApiQuery('https://stat.ripe.net/data/as-overview/data.json?resource=AS' . $asn);
        if (empty($asInfo['data']['holder']))
        {
            return null;
        }

        $asCountry = null;

        return [$asn, $asInfo['data']['holder'], $asCountry];
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function isNonInteractive(): bool
    {
        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

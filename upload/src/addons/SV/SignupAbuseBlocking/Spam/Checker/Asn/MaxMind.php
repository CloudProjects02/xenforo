<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Asn;

use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use SV\SignupAbuseBlocking\Repository\MaxMind as MaxMindRepo;
use SV\SignupAbuseBlocking\Spam\AsnProvider;

class MaxMind extends AsnProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->asnConfig['maxMind']) && (MaxMindRepo::get()->getAsnReader() !== null);
    }

    /** @noinspection PhpDuplicateCatchBodyInspection */
    public function resolveIpToAsn(string $ip): ?array
    {
        try
        {
            $record = MaxMindRepo::get()->getAsnReader()->asn($ip);
        }
        catch (InvalidDatabaseException $e)
        {
            return null;
        }
        catch (AddressNotFoundException $e)
        {
            return null;
        }

        return [$record->autonomousSystemNumber, $record->autonomousSystemOrganization, null];
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function isNonInteractive(): bool
    {
        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

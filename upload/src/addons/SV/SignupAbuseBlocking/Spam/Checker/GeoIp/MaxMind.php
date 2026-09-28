<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\GeoIp;

use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use SV\SignupAbuseBlocking\Repository\MaxMind as MaxMindRepo;
use SV\SignupAbuseBlocking\Spam\GeoIpProvider;

class MaxMind extends GeoIpProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->geoIpConfig['maxMind']) && (MaxMindRepo::get()->getGeoIpReader() !== null);
    }

    /** @noinspection PhpDuplicateCatchBodyInspection */
    public function resolveGeoIp(string $ip): ?string
    {
        try
        {
            $record = MaxMindRepo::get()->getGeoIpReader()->country($ip);
        }
        catch (InvalidDatabaseException $e)
        {
            return null;
        }
        catch (AddressNotFoundException $e)
        {
            return null;
        }

        return $record->country->isoCode ?? 'XX';
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function isNonInteractive(): bool
    {
        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

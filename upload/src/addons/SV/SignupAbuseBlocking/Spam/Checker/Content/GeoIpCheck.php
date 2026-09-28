<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use XF\Entity\User as UserEntity;
use function strcasecmp;

class GeoIpCheck extends AbstractUserRegistrationLogCheck
{
    protected function getType(): string
    {
        return 'svPostGeoip';
    }

    protected function getDefaultAction(): string
    {
        return \XF::options()->svGeoIpCheckActionOnNotMatch ?? 'allowed';
    }

    protected function resolveData(UserEntity $user): ?string
    {
        $ip = $this->app()->request()->getIp();
        $country = UserRegistrationLogRepo::get()->resolveCountryCode($ip, true, true);

        // failed to lookup geoip, just silently fail instead of throwing everything into the moderation queue
        if ($country === 'XX')
        {
            $country = null;
        }

        return $country;
    }

    protected function isEmpty(UserRegistrationLogEntity $logEntry): bool
    {
        return $logEntry->country === null || $logEntry->country === 'XX';
    }

    protected function isMatching(UserRegistrationLogEntity $logEntry, $data): bool
    {
        return strcasecmp($data, $logEntry->country) === 0;
    }

    protected function onNonMatch(UserRegistrationLogEntity $logEntry, $data, string $defaultAction): string
    {
        $this->logDetail('sv_reg_log.content_geoip_not_match', [
            'contentGeoIP' => $data,
            'regGeoIP'     => $logEntry->country,
        ]);

        return $defaultAction;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use XF\Entity\User as UserEntity;

class AsnCheck extends AbstractUserRegistrationLogCheck
{
    protected function getType(): string
    {
        return 'svPostAsn';
    }

    protected function getDefaultAction(): string
    {
        return \XF::options()->svAsnCheckActionOnNotMatch ?? 'allowed';
    }

    protected function resolveData(UserEntity $user): ?int
    {
        $ip = $this->app()->request()->getIp();
        $asnData = UserRegistrationLogRepo::get()->resolveAsn($ip, true, true);
        if (!$asnData)
        {
            return null;
        }
        $asn = (int)($asnData[1] ?? 0);
        if ($asn === 0)
        {
            return null;
        }

        return $asn;
    }

    protected function isEmpty(UserRegistrationLogEntity $logEntry): bool
    {
        return $logEntry->asn === null;
    }

    protected function isMatching(UserRegistrationLogEntity $logEntry, $data): bool
    {
        return $logEntry->asn === $data;
    }

    protected function onNonMatch(UserRegistrationLogEntity $logEntry, $data, string $defaultAction): string
    {
        $this->logDetail('sv_reg_log.content_geoip_not_match', [
            'contentAsn' => $data,
            'regAsn' => $logEntry->asn,
        ]);

        return $defaultAction;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

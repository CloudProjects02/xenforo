<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\SignupAbuseBlocking\Util\TimezoneList;
use XF\Entity\User as UserAlias;
use function trim, strlen, strcasecmp;

class TimezoneCheck extends AbstractUserRegistrationLogCheck
{
    protected function getType(): string
    {
        return 'svTimezoneAsn';
    }

    protected function getDefaultAction(): string
    {
        return \XF::options()->svTimezoneCheckActionOnNotMatch ?? 'allowed';
    }

    protected function resolveData(UserAlias $user): ?string
    {
        $timezone = trim((string)$this->app->request()->get('timezone', ''));
        if (strlen($timezone) === 0)
        {
            return null;
        }

        // skip checking the registration timezone as the user has updated to a custom value
        if (strcasecmp($timezone, $user->timezone) === 0)
        {
            return null;
        }

        // the registration timezone functionality is a subset of the full list
        if (isset(TimezoneList::$registrationTimezoneList[$timezone]))
        {
            return $timezone;
        }

        return null;
    }

    protected function isEmpty(UserRegistrationLogEntity $logEntry): bool
    {
        return $logEntry->timezone === null;
    }

    protected function isMatching(UserRegistrationLogEntity $logEntry, $data): bool
    {
        if ($logEntry->timezone === null)
        {
            return false;
        }

        return strcasecmp($logEntry->timezone, $data) === 0;
    }

    protected function onNonMatch(UserRegistrationLogEntity $logEntry, $data, string $defaultAction): string
    {
        $this->logDetail('sv_reg_log.content_timezone_not_match', [
            'contentTimezone' => $data,
            'regTimezone' => $logEntry->timezone,
        ]);

        return $defaultAction;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

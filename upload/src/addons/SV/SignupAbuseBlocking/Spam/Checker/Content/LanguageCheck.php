<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\SignupAbuseBlocking\Util\BrowserLanguage;
use XF\Entity\User as UserEntity;
use function strlen;

class LanguageCheck extends AbstractUserRegistrationLogCheck
{
    protected function getType(): string
    {
        return 'svTimezoneAsn';
    }

    protected function getDefaultAction(): string
    {
        return \XF::options()->svLanguageCheckActionOnNotMatch ?? 'allowed';
    }

    /**
     * @param array|null $langByPreference
     * @return array|null
     */
    protected function flattenLangToArray(?array $langByPreference): ?array
    {
        if (!$langByPreference)
        {
            return null;
        }

        $arr = [];
        foreach ($langByPreference as $languages)
        {
            foreach ($languages as $code)
            {
                $arr[$code] = true;
            }
        }

        return $arr;
    }

    protected function flattenLangToString($data): string
    {
        return implode(', ', $data);
    }

    protected function resolveData(UserEntity $user): ?array
    {
        $language = (string)$this->app()->request()->getServer('HTTP_ACCEPT_LANGUAGE', '');
        $language = trim($language);
        if (strlen($language) === 0)
        {
            return null;
        }

        return $this->flattenLangToArray(BrowserLanguage::parse($language));
    }

    protected function isEmpty(UserRegistrationLogEntity $logEntry): bool
    {
        return empty($logEntry->browser_language);
    }


    protected function isMatching(UserRegistrationLogEntity $logEntry, $data): bool
    {
        // Require the current browser language set to be a subset of the registration, or it fails to match

        $languages = $this->flattenLangToArray($logEntry->browser_language);
        foreach ($data as $code => $null)
        {
            if (!isset($languages[$code]))
            {
                return false;
            }
        }

        return true;
    }

    protected function onNonMatch(UserRegistrationLogEntity $logEntry, $data, string $defaultAction): string
    {
        $this->logDetail('sv_reg_log.content_language_not_match', [
            'contentLanguage' => $this->flattenLangToString($data),
            'regLanguage' => $this->flattenLangToString($logEntry->browser_language),
        ]);

        return $defaultAction;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

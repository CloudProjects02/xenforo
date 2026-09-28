<?php

namespace SV\SignupAbuseBlocking\Repository;

use SV\StandardLib\Helper;

/**
 * Class AllowedEmailDomain
 *
 * @package SV\SignupAbuseBlocking\Repository
 */
class AllowEmailDomain extends AbstractAllowOrBanItem
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public const ALLOWED_EMAIL_DOMAINS_CACHE_KEY = 'svSAB_a_e_d';

    public function valuesMatches(string $value, array $items)
    {
        $valueParts = \explode('@', $value, 2);
        if (\count($valueParts) !== 2)
        {
            return false; // wtf
        }
        $value = $valueParts[1];

        foreach ($items AS $item)
        {
            $itemTest = \str_replace('\\*', '(.*)', \preg_quote($item, '/'));
            if (\preg_match('/^' . $itemTest . '$/i', $value))
            {
                return $item;
            }
        }

        return false;
    }

    protected function getCacheKey(): string
    {
        return self::ALLOWED_EMAIL_DOMAINS_CACHE_KEY;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

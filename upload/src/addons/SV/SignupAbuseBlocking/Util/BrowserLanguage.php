<?php
/**
 * @noinspection SpellCheckingInspection
 */

namespace SV\SignupAbuseBlocking\Util;

use function strlen, count, explode, strpos, substr, strval, floatval, array_map, round, krsort, min, max, strcasecmp;

abstract class BrowserLanguage
{
    /**
     * @return array<float, string[]>|null
     */
    public static function parse(?string $languageGoop): ?array
    {
        if ($languageGoop === null || strlen($languageGoop) === 0)
        {
            return null;
        }

        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Language
        // https://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
        //       Accept-Language = "Accept-Language" ":"
        //                         1#( language-range [ ";" "q" "=" qvalue ] )
        //       language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" )

        $langByPreference = [];
        $languages = explode(',', $languageGoop);
        foreach ($languages as $language)
        {
            if (strlen($language) === 0)
            {
                continue;
            }

            $arr = explode(';', $language, 2);
            $arr = array_map('\trim', $arr);
            $lang = $arr[0] ?? '';
            if (strlen($lang) === 0)
            {
                continue;
            }

            if (strpos($lang, '*') === 0)
            {
                continue;
            }
            $lang = str_replace('-*', '', $lang);
            $variants = explode('-', $lang, 2);
            if (count($variants) === 2 && strcasecmp($variants[0], $variants[1]) === 0)
            {
                // simplify ru-RU or id-ID
                $lang = $variants[0];
            }

            $q = $arr[1] ?? '';
            if (strpos($q, 'q=') === 0)
            {
                $q = substr($q, 2);
                // type-juggling
                /** @noinspection PhpWrongStringConcatenationInspection */
                $pref = @strval(floatval($q)) + 0;
            }
            else
            {
                $pref = 1.0;
            }
            $pref = max($pref, 0.0);
            $pref = min($pref, 1.0);

            $intPref = (int)round(100 * $pref, 0, PHP_ROUND_HALF_UP);
            $langByPreference[$intPref][] = $lang;
        }

        if (count($langByPreference) === 0)
        {
            return null;
        }

        krsort($langByPreference, SORT_NUMERIC);

        return $langByPreference;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

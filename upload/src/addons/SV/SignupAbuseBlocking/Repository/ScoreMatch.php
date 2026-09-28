<?php


namespace SV\SignupAbuseBlocking\Repository;

use SV\StandardLib\Helper;
use XF\Mvc\Entity\Repository;
use XF\Util\Arr;
use function array_map;
use function array_values;
use function count;
use function explode;
use function is_array;
use function is_numeric;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function mb_strtolower;
use function trim;

class ScoreMatch extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    /**
     * @param string|string[]         $inputs
     * @param string                  $rules
     * @param ScoreMatchableInterface $onMatch
     * @param bool                    $numerical
     * @param bool                    $wildcard
     * @return bool
     */
    public function evaluateRules($inputs, string $rules, ScoreMatchableInterface $onMatch, bool $numerical, bool $wildcard): bool
    {
        $originalInputs = $inputs;
        $hasMatches = false;
        if (!is_array($inputs))
        {
            $inputs = [$inputs];
        }
        $inputs = array_values($inputs);
        $processedInputs = $intValInputs = [];
        foreach ($inputs as $input)
        {
            $processedInputs[] = trim(mb_strtolower($input));
        }
        if ($numerical)
        {
            foreach ($processedInputs as $input)
            {
                $intValInputs[] = (int)$input;
            }
        }
        $matchedInputs = [];
        $rules = mb_strtolower($rules);
        $splitRules = Arr::stringToArray($rules, '/\r?\n/');
        foreach ($splitRules as $splitRule)
        {
            $entry = explode('|', $splitRule, 2);
            if (count($entry) !== 2)
            {
                continue;
            }
            $entry = array_map('\trim', $entry);
            [$score, $matchText] = $entry;

            if ($wildcard)
            {
                $regex = preg_quote($matchText, '/');
                $regex = str_replace('\*', '.*', $regex);
                $regex = str_replace('\?', '.', $regex);
                $regex = str_replace('\\*', '[\w"\'/\s]*', $regex);
                $regex = preg_replace('#\s+#', '\\s+', $regex);
                $regex = '/^' . $regex . '$/iUu';
            }
            else
            {
                $regex = null;
            }

            foreach ($processedInputs as $key => $processedInput)
            {
                $match = false;

                if ($numerical && is_numeric($matchText))
                {
                    $intValInput = $intValInputs[$key];
                    $matchInt = (int)$matchText;
                    if ($matchInt === $intValInput)
                    {
                        $match = true;
                    }
                }
                // already converted to lowercase, so just do a direct match
                // note; doesn't handle unicode well :(
                else if ($processedInput === $matchText)
                {
                    $match = true;
                }
                else if ($regex)
                {
                    if (preg_match($regex, $processedInput))
                    {
                        $match = true;
                    }
                }

                if ($match)
                {
                    $matchInput = $inputs[$key];
                    $matchedInputs[$key] = true;
                    $hasMatches = true;
                    $continue = $onMatch->onRuleMatch($originalInputs, $score, $matchText, $matchInput);
                    if ($continue === false)
                    {
                        return true;
                    }
                }
            }
        }

        if (($onMatch instanceof ScoreInputNoMatchInterface) && count($matchedInputs) !== count($inputs))
        {
            $unmatchedInputs = [];
            foreach($inputs as $key => $value)
            {
                if (!isset($matchedInputs[$key]))
                {
                    $unmatchedInputs[$key] = $value;
                }
            }
            if (count($unmatchedInputs) !== 0)
            {
                $onMatch->onInputNoMatch($unmatchedInputs);
            }
        }

        return $hasMatches;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

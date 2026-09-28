<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Globals;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\ContentCheckerInterface;
use XF\Util\Arr as ArrUtil;

/**
 * Class RegSpamPhrases
 *
 * @package SV\SignupAbuseBlocking\Spam\Checker
 */
class RegSpamPhrases extends AbstractProvider implements ContentCheckerInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseRegSpamPhrases';
    }

    /**
     * @param UserEntity $user
     * @param string $message
     * @param array $extraParams
     */
    public function check(UserEntity $user, $message, array $extraParams = [])
    {
        $duringRegistration = Globals::$duringRegistration ?? false;
        if (!$duringRegistration)
        {
            return;
        }

        $option = $this->app()->options()->svRegSpamPhrases ?? [];
        $phrases = ArrUtil::stringToArray($option['phrases'] ?? '', '/\r?\n/');

        $decision = 'allowed';

        foreach ($phrases AS $phrase)
        {
            $phrase = \trim($phrase);
            if (\strlen($phrase) === 0)
            {
                continue;
            }

            $origPhrase = $phrase;

            if ($phrase[0] != '/')
            {
                $phrase = \preg_quote($phrase, '#');
                $phrase = \str_replace('\\*', '[\w"\'/ \t]*', $phrase);
                $phrase = '#(?<=\W|^)(' . $phrase . ')(?=\W|$)#iu';
            }
            else
            {
                if (\preg_match('/\W[\s\w]*e[\s\w]*$/', $phrase))
                {
                    // can't run a /e regex
                    continue;
                }
            }

            try
            {
                if (\preg_match($phrase, $message))
                {
                    $decision = $option['action'] == 'moderate' ? 'moderated' : 'denied';

                    $this->logDetail('svSignupBlocking_registration_spam_phrase_matched_x', [
                        'phrase' => $origPhrase
                    ]);

                    break;
                }
            }
            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (\ErrorException $e) {}
        }

        $this->logDecision($decision);
    }

    public function submitSpam($contentType, $contentIds)
    {
    }

    public function submitHam($contentType, $contentIds)
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

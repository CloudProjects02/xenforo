<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class Asn extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseAsn';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $ip = $this->app()->request()->getIp();
        $asnData = UserRegistrationLogRepo::get()->resolveAsn($ip, true, true);
        if (!$asnData)
        {
            return;
        }

        // note; we deliberately still run if there is no rules
        $asnBlockingRule = \XF::options()->svSignupAsnBlockingRule ?? '';
        $hasMatches = ScoreMatchRepo::get()->evaluateRules($asnData, $asnBlockingRule, $this, true, true);
        if (!$hasMatches)
        {
            $this->onRuleMatch($asnData, 0, '', '');
        }
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        [$asNumber, $asName] = $input;
        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.as_fail', ['number' => $asNumber, 'name' => $asName]);
                break;
            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.as_fail', ['number' => $asNumber, 'name' => $asName]);
                break;
            case '0':
                $checker->logScoreAccept('sv_reg_log.as_ok', ['number' => $asNumber, 'name' => $asName]);
                break;
            default:
                $checker->logScore('sv_reg_log.as_fail', $score, ['number' => $asNumber, 'name' => $asName]);
                break;
        }

        // stop on first match
        return false;
    }


    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

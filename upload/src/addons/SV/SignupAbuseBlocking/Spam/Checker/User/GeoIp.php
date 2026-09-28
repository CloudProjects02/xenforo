<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserAlias;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class GeoIp extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseGeoIp';
    }

    public function check(UserAlias $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $ip = $this->app()->request()->getIp();
        $countryCode = UserRegistrationLogRepo::get()->resolveCountryCode($ip, true, true);
        if ($countryCode === null)
        {
            return;
        }

        $hasMatches = ScoreMatchRepo::get()->evaluateRules($countryCode, $this->app->options()->svSignupCountryBlockingRule ?? '', $this, false, true);
        if (!$hasMatches)
        {
            $this->onRuleMatch($countryCode, 0, '', $countryCode);
        }
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.country_fail', ['country' => $matchInput]);
                break;
            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.country_fail', ['country' => $matchInput]);
                break;
            case '0':
                $checker->logScoreAccept('sv_reg_log.country_ok', ['country' => $matchInput]);
                break;
            default:
                $checker->logScore('sv_reg_log.country_fail', $score, ['country' => $matchInput]);
                break;
        }

        // keep going
        return true;
    }

    public function submit(UserAlias $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class Hostname extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseHostname';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        // note; we deliberately still run if there is no rules
        $hostnameConfig = \XF::options()->svSignupHostnameBlockingRule ?? '';

        $ip = \XF::app()->request()->getIp();
        if (!$ip)
        {
            return;
        }

        $hostname = @gethostbyaddr($ip);

        $hasMatches = ScoreMatchRepo::get()->evaluateRules($hostname, $hostnameConfig, $this, false, true);
        if (!$hasMatches)
        {
            $this->onRuleMatch($hostname, 0, '', $hostname);
        }
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.hostname_fail', ['hostname' => $matchInput]);
                break;
            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.hostname_fail', ['hostname' => $matchInput]);
                break;
            case '0':
                $checker->logScoreAccept('sv_reg_log.hostname_ok', ['hostname' => $input]);
                break;
            default:
                $checker->logScore('sv_reg_log.hostname_fail', $score, ['hostname' => $input]);
                break;
        }

        // keep going
        return true;
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

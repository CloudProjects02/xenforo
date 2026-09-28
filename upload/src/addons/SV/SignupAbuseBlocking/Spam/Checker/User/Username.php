<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class Username extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseUsername';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $usernameConfig = \XF::options()->svSignupUsernameBlockingRule ?? '';
        if (\strlen($usernameConfig) === 0)
        {
            return;
        }

        $username = $user->username;

        ScoreMatchRepo::get()->evaluateRules($username, $usernameConfig, $this, false, true);
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.username_fail', ['username' => $matchInput]);
                break;
            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.username_fail', ['username' => $matchInput]);
                break;
            case '0':
                $checker->logScoreAccept('sv_reg_log.username_fail', ['username' => $matchInput]);
                break;
            default:
                $checker->logScore('sv_reg_log.username_fail', $score, ['username' => $matchInput]);
                break;
        }

        // keep going
        return true;
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class Email extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseEmail';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $emailConfig = \XF::options()->svSignupEmailBlockingRule ?? '';
        if (\strlen($emailConfig) === 0)
        {
            return;
        }

        $email = $user->email;

        ScoreMatchRepo::get()->evaluateRules($email, $emailConfig, $this, false, true);
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.email_fail', ['email' => $matchInput]);
                break;
            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.email_fail', ['email' => $matchInput]);
                break;
            case '0':
                $checker->logScoreAccept('sv_reg_log.email_fail', ['email' => $matchInput]);
                break;
            default:
                $checker->logScore('sv_reg_log.email_fail', $score, ['email' => $matchInput]);
                break;
        }

        // keep going
        return true;
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

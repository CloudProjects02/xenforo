<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Repository\AllowEmailDomain as AllowEmailDomainRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

/**
 * Class EmailDomain
 *
 * @package SV\SignupAbuseBlocking\Spam\Checker
 */
class EmailDomain extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseEmailDomain';
    }

    /**
     * @param UserEntity $user
     * @param array $extraParams
     */
    public function check(UserEntity $user, array $extraParams = [])
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $email = $user->email;
        $duringRegistration = Globals::$duringRegistration ?? false;
        if (\strlen($email) === 0 || !$duringRegistration)
        {
            $this->logDecision('allowed');
            return;
        }

        list($localPart, $domain) = \explode('@', $email, 2);

        $allowEmailRepo = AllowEmailDomainRepo::get();
        if ($allowEmailRepo->valueMatchesFromCache($email))
        {
            /** @var IScoringChecker $checker */
            $checker = $this->checker;
            $checker->logScore('sv_reg_log.email_domain_whitelist_accept', 0, ['email' => $email, 'local' => $localPart, 'domain' => $domain]);

            $this->logDecision('allowed');
            return;
        }

        $emailConfig = \XF::options()->svSignupAbuseBlocking_nonAllowedEmailAction ?? '';
        if (\strlen($emailConfig) === 0)
        {
            $this->logDecision('allowed');
            return;
        }

        $this->onRuleMatch(
            [$email, $localPart, $domain],
            $emailConfig,
            '', ''
        );
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        list($email, $localPart, $domain) = $input;

        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.email_domain_whitelist_fail', ['email' => $email, 'local' => $localPart, 'domain' => $domain]);
                break;

            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.email_domain_whitelist_fail', ['email' => $email, 'local' => $localPart, 'domain' => $domain]);
                break;

            case '0':
                $checker->logScoreAccept('sv_reg_log.email_domain_whitelist_ok', ['email' => $email, 'local' => $localPart, 'domain' => $domain]);
                break;

            default:
                $checker->logScore('sv_reg_log.email_domain_whitelist_fail', $score, ['email' => $email, 'local' => $localPart, 'domain' => $domain]);
                break;
        }

        // stop on first match
        return false;
    }

    /**
     * @param UserEntity $user
     * @param array $extraParams
     */
    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

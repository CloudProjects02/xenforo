<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\ICountryScorer;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;
use function strlen;
use function strval;
use function trim;

/**
 * @property-read IScoringChecker|ICountryScorer $checker
 */
class Timezone extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseTimezone';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        $checker = $this->checker;
        if (!($checker instanceof IScoringChecker))
        {
            return;
        }

        // XF detects the timezone via javascript, and it is mostly a guess to resolve the named zone
        $timezone = $user->timezone;
        $timezone = trim($timezone);
        if (strlen($timezone) === 0)
        {
            return;
        }

        $noMatch = true;
        $config = [
            'rules' => \XF::options()->svSignupTimezoneBlockingRule ?? '',
            'unknown' => \XF::options()->svSignupUnknownTimezoneRule ?? '0',
        ];
        if ($this->validateConfig($config))
        {
            $checker->setBrowserTimezone($timezone);

            $country = $checker->getCountry();
            if ($country !== '')
            {
                $input = $country . '-' . $timezone;
                $hasMatches = ScoreMatchRepo::get()->evaluateRules($input, $config['rules'], $this, false, true);
                if (!$hasMatches)
                {
                    $this->checker->logScore('sv_reg_log.user_timezone_fail', $config['unknown'], [
                        'country'  => $country,
                        'timezone' => $input
                    ]);
                }
                $noMatch = false;
            }
        }

        if ($noMatch)
        {
            $checker->logScore('sv_reg_log.user_timezone_unknown', 0, ['timezone' => $timezone]);
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function validateConfig(array $config): bool
    {
        if (!($this->checker instanceof ICountryScorer))
        {
            return false;
        }

        return true;
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        $parts = explode('-', $input, 2);
        $country = $parts[0] ?? '';
        $timezone = $parts[1] ?? '';

        switch (strval($score))
        {
            case 'reject':
                $this->checker->logScoreReject('sv_reg_log.user_timezone_fail', [
                    'country'  => $country,
                    'timezone' => $timezone
                ]);
                break;
            case 'moderate':
                $this->checker->logScoreModerate('sv_reg_log.user_timezone_fail', [
                    'country'  => $country,
                    'timezone' => $timezone
                ]);
                break;
            case '0':
                $this->checker->logScore('sv_reg_log.user_timezone', 0, [
                    'country'  => $country,
                    'timezone' => $timezone
                ]);
                break;
            default:
                $this->checker->logScore('sv_reg_log.user_timezone_fail', $score, [
                    'country'  => $country,
                    'timezone' => $timezone
                ]);
                break;
        }

        // keep going
        return true;
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

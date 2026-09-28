<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreInputNoMatchInterface;
use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\ICountryScorer;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use SV\SignupAbuseBlocking\Util\BrowserLanguage;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;
use function array_keys;
use function array_unique;
use function preg_match;
use function preg_quote;
use function strcasecmp;
use function strlen;
use function strval;
use function trim;

/**
 * @property-read IScoringChecker $checker
 */
class Language extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface, ScoreInputNoMatchInterface
{
    /** @var array */
    protected $config = [];

    protected function getType(): string
    {
        return 'SignupAbuseLanguage';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        $checker = $this->checker;
        if (!($checker instanceof IScoringChecker))
        {
            return;
        }

        $language = (string)$this->app()->request()->getServer('HTTP_ACCEPT_LANGUAGE', '');
        $language = trim($language);

        if (strlen($language) === 0)
        {
            return;
        }

        $noMatch = true;
        $this->config = [
            'rules'   => \XF::options()->svSignupLanguageBlockingRule ?? '',
            'unknown' => \XF::options()->svSignupUnknownLanguageRule ?? '0',
        ];

        if ($this->validateConfig($this->config))
        {
            $checker->setBrowserLanguages($language);
            $langByPreference = BrowserLanguage::parse($language);

            $country = $checker->getCountry();
            if ($country === '')
            {
                $country = 'XX';
            }
            if ($langByPreference)
            {
                $inputs = [];
                foreach ($langByPreference as $languages)
                {
                    foreach ($languages as $code)
                    {
                        $input = $country . '-' . $code;
                        if (isset($inputs[$input]))
                        {
                            continue;
                        }
                        $inputs[$input] = true;
                    }
                }
                $inputs = array_keys($inputs);

                $rules = $this->config['rules'];
                // fold `country-lang-country` => `country-lang` ie `US-en-US` => `US-en`. IFF there is no rule for it
                $inputs = array_unique(array_map(function($value) use ($rules) {
                    $parts = explode('-', $value);
                    if (count($parts) === 3 && strcasecmp($parts[0], $parts[2]) === 0)
                    {
                        if (!preg_match('/^[^|]+\|\s*'.preg_quote($value).'\s*$/mi', $rules))
                        {
                            return $parts[0] . '-' . $parts[1];
                        }
                    }

                    return $value;
                }, $inputs));

                ScoreMatchRepo::get()->evaluateRules($inputs, $rules, $this, false, true);
                $noMatch = false;
            }
        }

        if ($noMatch)
        {
            $checker->logScore('sv_reg_log.user_language_unknown', 0, ['language' => $language]);
        }
    }

    public function onInputNoMatch(array $inputs)
    {
        $unknownScore = strval($this->config['unknown'] ?? '');
        if (is_numeric($unknownScore) || $unknownScore === '')
        {
            $unknownScore = 0;
        }
        $scoreOnce = \XF::options()->svSignupUnknownLanguageScoreOnce ?? false;
        foreach ($inputs as $input)
        {
            $parts = explode('-', $input, 2);
            $country = $parts[0] ?? '';
            $language = $parts[1] ?? '';

            if ($scoreOnce && $unknownScore !== 0)
            {
                $unknownScore = 0;
            }
            $this->checker->logScore('sv_reg_log.user_language_unknown', $unknownScore, [
                'country'  => $country,
                'language' => $language,
            ]);
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
        $parts = explode('-', $matchInput, 2);
        $country = $parts[0] ?? '';
        $language = $parts[1] ?? '';
        switch (strval($score))
        {
            case 'reject':
                $this->checker->logScoreReject('sv_reg_log.user_language_fail', [
                    'country'  => $country,
                    'language' => $language,
                ]);
                break;
            case 'moderate':
                $this->checker->logScoreModerate('sv_reg_log.user_language_fail', [
                    'country'  => $country,
                    'language' => $language,
                ]);
                break;
            case '0':
                $this->checker->logScore('sv_reg_log.user_language', 0, [
                    'country'  => $country,
                    'language' => $language,
                ]);
                break;
            default:
                $this->checker->logScore('sv_reg_log.user_language_fail', $score, [
                    'country'  => $country,
                    'language' => $language,
                ]);
        }

        // keep going
        return true;
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

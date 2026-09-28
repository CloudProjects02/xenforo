<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Spam;

use SV\SignupAbuseBlocking\Repository\UserAgent as UserAgentRepo;
use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\SignupAbuseBlocking\Spam\ICountryScorer;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Entity\UserGroup as UserGroupEntity;
use XF\Util\Ip;
use function array_key_exists;
use function is_string, preg_match, array_keys, implode, array_merge, is_numeric, strtolower, time, json_encode;

/**
 * @Extends \XF\Spam\UserChecker
 */
class UserChecker extends XFCP_UserChecker implements IScoringChecker, ICountryScorer
{
    protected $scores = [];

    /**
     * @param string $phrase
     * @param array  $phraseData
     */
    public function logSimpleDetail(string $phrase, array $phraseData = [])
    {
        $this->details[] = [
            'phrase' => $phrase,
            'data'   => $phraseData,
        ];
    }

    /**
     * @param string           $phrase
     * @param string|float|int $score
     * @param array            $phraseData
     */
    public function logScore(string $phrase, $score, array $phraseData = [])
    {
        $this->scores[] = $score;

        $phraseData = array_merge([
            'score' => $score,
        ], $phraseData);

        $this->details[] = [
            'phrase' => $phrase,
            'data'   => $phraseData,
        ];
    }

    public function logScoreAccept(string $phrase, array $phraseData = [])
    {
        $this->logScore($phrase, 'accept', $phraseData);
    }
    public function logScoreReject(string $phrase, array $phraseData = [])
    {
        $this->logScore($phrase, 'reject', $phraseData);
    }

    public function logScoreModerate(string $phrase, array $phraseData = [])
    {
        $this->logScore($phrase, 'moderate', $phraseData);
    }

    protected $patchingLogDecision = false;

    public function patchLogDecision(bool $start): bool
    {
        $old = $this->patchingLogDecision;
        $this->patchingLogDecision = $start;

        return $old;
    }

    public function logDecision($type, $decision)
    {
        if ($this->patchingLogDecision)
        {
            return;
        }
        parent::logDecision($type, $decision);
    }

    protected function getFinalScore(): array
    {
        $scoreTotal = 0;
        $reject = false;
        $moderate = false;
        $moderatePosts = false;
        $addToGroup = false;
        foreach ($this->scores as $score)
        {
            if (is_numeric($score))
            {
                $scoreTotal += (int)$score;
            }
            else
            {
                switch(strtolower($score))
                {
                    case 'reject':
                        $reject = true;
                        break;
                    case 'moderate':
                        $moderate = true;
                        break;
                    case 'moderateposts':
                        $moderatePosts = true;
                        break;
                    case 'addtogroup':
                        $addToGroup = true;
                        break;
                }
            }
        }

        return [$scoreTotal, $reject, $moderate, $moderatePosts, $addToGroup];
    }

    public function getFinalDecision()
    {
        // only compute the score once
        if (array_key_exists('score', $this->decisions))
        {
            return parent::getFinalDecision();
        }

        // compute final decision from score
        /** @noinspection PhpUnusedLocalVariableInspection */
        [$score, $reject, $moderate, $moderatePosts, $addToGroup] = $this->getFinalScore();
        $this->logSimpleDetail('sv_reg_log.total_score', ['score' => $score]);

        $options = \XF::options();
        $addToGroupId = $options->svSignupAddToGroup ?? 0;
        $addToGroupThreshold = $addToGroupId ? $options->svSignupAddToGroupThreshold ?? 0 : 0;
        $rejectScoreThreshold = $options->svSignupRejectScoreThreshold ?? 0;
        $moderateScoreThreshold = $options->svSignupModerateScoreThreshold ?? 0;
        if ($addToGroupId && ($addToGroup || ($addToGroupThreshold && $score >= $addToGroupThreshold)))
        {
            $userGroup = Helper::find(UserGroupEntity::class, $addToGroupId);
            if ($userGroup && $userGroup->user_group_id)
            {
                $this->logSimpleDetail('sv_reg_log.add_to_group', ['groupName' => $userGroup->title]);
                /** @var UserEntity $user */
                $user = $this->user;
                $groups = $user->secondary_group_ids;
                $groups[] = $userGroup->user_group_id;
                $user->secondary_group_ids = \array_unique($groups);
                // only save the user if they already exist, otherwise they will be saved when registration completes...
                if ($user->exists())
                {
                    $user->saveIfChanged();
                }
            }
        }
        if ($reject)
        {
            $this->logDecision('score', 'denied');
            $this->logSimpleDetail('sv_reg_log.direct_rule_reject');
        }
        else if ($rejectScoreThreshold && $score >= $rejectScoreThreshold)
        {
            $this->logDecision('score', 'denied');
            $this->logSimpleDetail('sv_reg_log.fail_score_rejected', ['score' => $score, 'required' => $rejectScoreThreshold]);
        }
        else if ($moderate)
        {
            $this->logDecision('score', 'moderated');
            $this->logSimpleDetail('sv_reg_log.direct_rule_moderated');
        }
        else if ($moderateScoreThreshold && $score >= $moderateScoreThreshold)
        {
            $this->logDecision('score', 'moderated');
            $this->logSimpleDetail('sv_reg_log.fail_score_moderated', ['score' => $score, 'required' => $moderateScoreThreshold]);
        }
        //else if ($moderatePostScoreThreshold && $score > $moderatePostScoreThreshold)
        //{
        // $this->logDecision('score', 'allowed');
        // $this->logSimpleDetail('sv_reg_log.fail_score_posts_moderated', ['score' => $score, 'required' => $moderatePostScoreThreshold]);
        //}
        //else if ($moderatePosts)
        //{
        // $this->logDecision('score', 'allowed');
        // $this->logSimpleDetail('sv_reg_log.direct_rule_posts_moderated');
        //}
        else
        {
            $this->logDecision('score', 'allowed');
        }

        return parent::getFinalDecision();
    }

    protected $user;

    public function check(UserEntity $user, array $extraParams = [])
    {
        $this->user = $user;
        $request = $this->app()->request();
        $this->logSimpleDetail('sv_reg_log.checking', ['username' => $user->username, 'email' => $user->email, 'ip' => $request->getIp()]);

        parent::check($user, $extraParams);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function logSpamTrigger($contentType, $contentId)
    {
        $finalDecision = $this->getFinalDecision();

        if ($contentType === 'user' && $this->user && $this->user->user_id === $contentId)
        {
            $this->logUserRegistrationRecord($finalDecision);
        }

        // https://xenforo.com/community/threads/logspamtrigger-does-not-match-getfinaldecision-resulting-in-the-wrong-action-type-being-logged.222001/
        // force logSpamTrigger to behave sanely


        $oldDecisions = $this->decisions;
        $this->decisions = [$finalDecision];
        try
        {
            return parent::logSpamTrigger($contentType, $contentId);
        }
        finally
        {
            $this->decisions = $oldDecisions;
        }
    }

    public function logUserRegistrationRecord(string $finalDecision): bool
    {
        $values = $this->populateAdditionalDataToLog();
        $request = $this->app()->request();

        $ip = $request->getIp();
        $ipAddress = $ip ? Ip::convertIpStringToBinary($ip) : '';
        $userAgent = (string)$request->getUserAgent();

        $request = [
            'url'      => $request->getRequestUri(),
            'referrer' => $request->getServer('HTTP_REFERER', ''),
            '_GET'     => $_GET,
            '_POST'    => $request->filterForLog($_POST)
        ];

        $userAgentId = UserAgentRepo::get()->logUserAgent($userAgent);

        $values = array_merge([
            'log_date'      => time(),
            'user_id'       => $this->user->user_id,
            'username'      => $this->user->username,
            'ip_address'    => $ipAddress,
            'result'        => $finalDecision,
            'details'       => json_encode($this->details),
            'request_state' => json_encode($request),
            'user_agent_id' => $userAgentId,
        ], $values);

        $updateOnExists = $this->getKeysToUpdate($values);

        $onDupe = [];
        foreach ($updateOnExists AS $update)
        {
            $onDupe[] = "$update = VALUES($update)";
        }
        $onDupe = implode(', ', $onDupe);

        $db = $this->app()->db();
        UserRegistrationLogRepo::get()->logSignupRecordForRateLimit($this->user, $ip, [
            'user_agent_id' => $userAgentId,
            'asn' => $values['asn'] ?? null,
            'country' => $values['country'] ?? null,
        ]);
        $rows = $db->insert('xf_sv_user_registration_log', $values, false, $onDupe);

        return $rows == 1 ? $db->lastInsertId() : true;
    }

    /** @var string|null */
    protected $country = null;

    public function getCountry(): string
    {
        if ($this->country === null)
        {
            $phraseData = $this->extractByPhrases('/^sv_reg_log\.country_/');
            $country = $phraseData['country'] ?? null;
            if (!is_string($country) || $country === 'XX')
            {
                $country = '';
            }
            $this->country = $country;
        }

        return $this->country;
    }

    /** @var string */
    protected $languages = '';

    public function getBrowserLanguages(): string
    {
        return $this->languages;
    }

    public function setBrowserLanguages(string $languages)
    {
        $this->languages = $languages;
    }

    /** @var string */
    protected $timezone = '';

    public function getBrowserTimezone(): string
    {
        return $this->timezone;
    }

    public function setBrowserTimezone(string $timezone)
    {
        $this->timezone = $timezone;
    }

    protected function populateAdditionalDataToLog(): array
    {
        $values = [];

        $phraseData = $this->extractByPhrases('/^sv_reg_log\.as_/');
        $asn = $phraseData['number'] ?? null;
        if ($asn !== null)
        {
            $values['asn'] = $asn;
        }

        $country = $this->getCountry();
        if ($country !== '')
        {
            $values['country'] = $country;
        }

        $languages = $this->getBrowserLanguages();
        if ($languages)
        {
            $values['browser_language'] = $languages;
        }

        $timezone = $this->getBrowserTimezone();
        if ($timezone)
        {
            $values['timezone'] = $timezone;
        }

        return $values;
    }

    protected function getKeysToUpdate(array $values): array
    {
        unset($values['username']);
        return array_keys($values);
    }

    protected function extractByPhrases(string $string): array
    {
        foreach($this->details as $detail)
        {
            if (preg_match($string, $detail['phrase'] ?? ''))
            {
                return $detail['data'] ?? [];
            }
        }

        return [];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethod;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionRecord;
use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Entity\ChangeLog as ChangeLogEntity;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;
use XF\Util\Arr;
use function array_merge;
use function array_unique;
use function strtolower;

class MultipleAccount extends AbstractProvider implements UserCheckerInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseMultiAccount';
    }

    public function check(UserEntity $user, array $extraParams = [])
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            // not configured
            return;
        }
        /** @var ExtendedUserEntity $user */
        $multipleAccountRepo = MultipleAccountRepo::get();
        $token = $multipleAccountRepo->getCookieValue('register');

        Globals::$dataCollection = null;
        try
        {
            $dataCollection = $multipleAccountRepo->detectMultipleAccounts($user, $token);
            $this->doCheck($this->checker, $dataCollection);
            Globals::$dataCollection = $dataCollection;
        }
        catch (\Throwable $e)
        {
            // do not block login if any sort of error occurs
            \XF::logException($e, true);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
        }
    }

    /**
     * @param IScoringChecker   $checker
     * @param null|DetectionRecord[] $dataCollection
     */
    protected function doCheck(IScoringChecker $checker, ?array $dataCollection)
    {
        $options = $this->app()->options();
        $db = $this->app()->db();

        $registrationMode = $options->svSockSignupCheckRegMode ?? '';
        $registrationModeForBanned = $options->svSockSignupCheckRegModeGroup ?? '';
        $specialGroupIds = $options->svSockSignupCheckRegModeGroupIds ?? '';
        $specialPermCheckString = $options->svSockSignupCheckRegModePerms ?? '';
        if ($specialGroupIds)
        {
            $specialGroupIds = array_map('\intval', explode(',', $specialGroupIds));
        }
        else
        {
            $specialGroupIds = [];
        }
        $specialPermChecks = [];
        $splitRules = Arr::stringToArray($specialPermCheckString, '/\r?\n/');
        $splitRules = array_unique($splitRules);
        foreach($splitRules as $rule)
        {
            $rule = trim($rule);
            if ($rule === '')
            {
                continue;
            }

            $parts = explode(':', $rule, 3);
            if (count($parts) < 2)
            {
                continue;
            }

            $permGrantType = strtolower($parts[2] ?? '*');
            if ($permGrantType !== 'allow' && $permGrantType !== 'deny' && $permGrantType !== '*')
            {
                continue;
            }
            $parts[2] = $permGrantType;

            $specialPermChecks[] = $parts;
        }

        $action = 'allowed';
        if (!$dataCollection)
        {
            $this->logDecision($action);

            return;
        }

        $typeId = 0;
        foreach ($dataCollection as $data)
        {
            $typeId++;
            $actionToTake = $registrationMode;

            /**
             * @var string          $detectionMethod
             * @var DetectionMethod $detectionData
             */
            $methods = [];
            foreach ($data->methods as $detectionMethod => $detectionData)
            {
                if ($data->user->canBypassMultipleAccountDetection($detectionMethod))
                {
                    continue;
                }
                $methods[] = \XF::phrase('sv_multiple_account_method.' . $detectionMethod, $detectionData->forPhraseData())->render('raw');
            }
            if (!$methods)
            {
                continue;
            }
            $methods = implode(', ', $methods);

            $otherUserId = $data->user->user_id;
            $phraseData = [
                'method'   => $methods,
                'username' => $data->user->username,
                'user_id'  => $otherUserId,
            ];

            if ($data->user->is_banned)
            {
                $actionToTake = $registrationModeForBanned;
                $this->logDetail2($typeId, '_banned', 'sv_reg_log.multi_account_is_banned', $phraseData);
            }

            if ($registrationModeForBanned)
            {
                $groups = $data->user->secondary_group_ids;
                $groups[] = $data->user->user_group_id;
                $intersect = array_intersect($groups, $specialGroupIds);

                if ($intersect)
                {
                    $actionToTake = $registrationModeForBanned;

                    $changeLog = Helper::createEntity(ChangeLogEntity::class);
                    $changeLog->content_type = 'user';
                    $changeLog->old_value = '';
                    $changeLog->new_value = implode(',', $intersect);
                    $changeLog->field = 'secondary_group_ids';
                    $changeLog->setReadOnly(true);

                    $displayLog = $changeLog->getDisplayEntry();
                    $groupPhraseData = $phraseData;
                    $groupPhraseData['groups'] = $displayLog->new;

                    $this->logDetail2($typeId, '_member', 'sv_reg_log.multi_account_group', $groupPhraseData);
                }

                foreach ($specialPermChecks as $permCheck)
                {
                    [$group, $id, $permGrantType] = $permCheck;

                    // check general permissions
                    if ($permGrantType === 'allow')
                    {
                        $permissionCheck = "permission_value = 'allow' OR (permission_value = 'use_int' AND ? = 'allow')";
                    }
                    else if ($permGrantType === 'deny')
                    {
                        $permissionCheck = "permission_value = 'deny'";
                    }
                    else
                    {
                        $permissionCheck = '1=1';
                    }
                    $value = $db->fetchOne("
                        SELECT permission_value
                        FROM xf_permission_entry 
                        WHERE user_id = ? AND permission_group_id = ? AND permission_id = ? AND ({$permissionCheck})
                        LIMIT 1
                    ", [$otherUserId, $group, $id]);
                    if ($value)
                    {
                        $actionToTake = $registrationModeForBanned;
                        $this->logDetail2($typeId, '_member', 'sv_reg_log.multi_account_perm', array_merge([
                            'perm'   => $group . '_' . $id,
                            'action' => $permGrantType,
                            'value'  => $value,
                        ], $phraseData));
                    }

                    // check per-content type permissions
                    if ($permGrantType === 'allow')
                    {
                        $permissionCheck = "permission_value = 'content_allow' OR (permission_value = 'use_int' AND ? = 'content_allow')";
                    }
                    else if ($permGrantType === 'deny')
                    {
                        $permissionCheck = "permission_value = 'deny' OR permission_value = 'reset'";
                    }
                    else
                    {
                        $permissionCheck = '1=1';
                    }
                    $row = $db->fetchRow("
                        SELECT DISTINCT content_type, content_id, permission_value
                        FROM xf_permission_entry_content 
                        WHERE user_id = ? AND permission_group_id = ? AND permission_id = ? AND ({$permissionCheck})
                        LIMIT 1
                    ", [$otherUserId, $group, $id]);
                    if ($row)
                    {
                        $actionToTake = $registrationModeForBanned;
                        $this->logDetail2($typeId, '_member', 'sv_reg_log.multi_account_perm_content', array_merge([
                            'perm'        => $group . '_' . $id,
                            'action'      => $permGrantType,
                            'contentType' => $row['content_type'],
                            'contentId'   => $row['content_id'],
                            'value'       => $row['permission_value'],
                            'title'       => $row['content_type'] . ':' . $row['content_id'],
                        ], $phraseData));
                    }
                }
            }

            switch ($actionToTake)
            {
                case 'reject':
                    $checker->logScoreReject('sv_reg_log.multi_account_reject', $phraseData);
                    break;
                case 'moderate':
                    $checker->logScoreModerate('sv_reg_log.multi_account_moderate', $phraseData);
                    break;
                case '0':
                    $checker->logScoreAccept('sv_reg_log.multi_account_accept', $phraseData);
                    break;
                default:
                    // this really should be sv_reg_log.multi_account_fail, but to avoid needing to migrate data it is not renamed
                    $checker->logScore('sv_reg_log.multi_account_accept_score', $actionToTake, $phraseData);
                    break;
            }
        }

        $this->logDecision($action);
    }

    protected function logDetail2(int $typeId, string $typeSuffix, string $phrase, array $data = [])
    {
        $this->checker->logDetail($this->getType() . '.' . $typeId . $typeSuffix, $phrase, $data);
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

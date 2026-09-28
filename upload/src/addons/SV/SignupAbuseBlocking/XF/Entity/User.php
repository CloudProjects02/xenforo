<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Entity;

use SV\SignupAbuseBlocking\Entity\MultiAccountUser as MultiAccountUserEntity;
use SV\SignupAbuseBlocking\Entity\Token as TokenEntity;
use SV\SignupAbuseBlocking\Entity\UserLoginLog;
use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\SignupAbuseBlocking\Finder\Log as LogFinder;
use SV\SignupAbuseBlocking\Finder\UserLoginLog as UserLoginLogFinder;
use SV\SignupAbuseBlocking\Finder\UserRegistrationLog as UserRegistrationLogFinder;
use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Repository\AllowEmailDomain as AllowEmailDomainRepo;
use SV\StandardLib\BypassAccessStatus;
use XF\Db\DuplicateKeyException;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Validator\Email as EmailValidator;
use function is_callable;

/**
 * GETTERS
 *
 * @property-read TokenEntity                                   $AccountDetectionToken
 * @property-read bool                                          $HasLoginRecords
 * @property-read bool                                          $HasMultiAccounts
 * @property-read bool                                          $HasRegistrationLogs
 * @property-read UserLoginLog                                  $svLastLogin
 * RELATIONS
 * @property-read ?TokenEntity                                  $AccountDetectionToken_
 * @property-read ?UserProfile                                  $Profile
 * @property-read AbstractCollection<UserRegistrationLogEntity> $UserRegistrationLogs
 * @property-read ?MultiAccountUserEntity                       $MultiAccountUser
 */
class User extends XFCP_User
{
    public function canViewMultiAccountReport(): bool
    {
        if (!$this->user_id)
        {
            return false;
        }

        return $this->hasPermission('multipleAccountHandling', 'viewreport');
    }

    public function canChangeUserMultiAccountAlerting(): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id || !$this->user_id)
        {
            return false;
        }

        return $visitor->hasPermission('multipleAccountHandling', 'userAlerting');
    }

    /**
     * @param string $method
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function canBypassMultipleAccountDetection(string $method): bool
    {
        if (!$this->user_id)
        {
            return false;
        }

        $xfOptions = $this->app()->options();

        if ($this->is_banned && ($xfOptions->svAlwaysSockCheckBanned ?? false))
        {
            return false;
        }

        if ($this->hasPermission('multipleAccountHandling', 'bypass'))
        {
            return true;
        }

        return false;
    }

    /**
     * @param bool $automaticApprovalResult
     * @return bool
     */
    public function isRequireEmailConfirmationFromApprovalQueue(bool $automaticApprovalResult = true): bool
    {
        $registrationSetup = \XF::options()->registrationSetup;
        $moderation = $registrationSetup['moderation'];
        $emailConfirmation = $registrationSetup['emailConfirmation'];
        if ($emailConfirmation)
        {
            if (!$moderation)
            {
                return $automaticApprovalResult;
            }
            else
            {
                // spam checker pushes straight into the moderated state
                // while normal signup does user registration => email confirmation => approval queue
                // check the last logs to determine if the user was pushed into the spam trigger log
                /** @var UserRegistrationLogEntity $log */
                $log = $this->getRelationFinder('UserRegistrationLogs')
                     ->order('log_date', 'DESC')
                     ->fetchOne();

                return $log && $log->wasModerated();
            }
        }

        return false;
    }

    protected function getAccountDetectionToken(): TokenEntity
    {
        /** @var TokenEntity $token */
        $token = $this->getRelationOrDefault('AccountDetectionToken', false);

        if (!$token->exists())
        {
            $token->active = true;
            $token->generateToken();
            if ($this->user_id)
            {
                try
                {
                    $token->save();
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch (DuplicateKeyException $e)
                {
                    // this occurs if a user somehow manages to login-twice at exactly the same time (dodgy 3rd party add-on?)
                    $realToken = $this->getRelationFinder('AccountDetectionToken')->fetchOne();
                    if ($realToken)
                    {
                        $this->hydrateRelation('AccountDetectionToken', $realToken);
                        $token = $realToken;
                    }
                }
            }
        }

        return $token;
    }

    public function svSpamCloneEntity(?Entity $entity = null, array $seen = [])
    {
        if ($entity === null)
        {
            $entity =  $this;
        }

        if (isset($seen[$entity->getUniqueEntityId()]))
        {
            return $seen[$entity->getUniqueEntityId()];
        }

        $accesser = new BypassAccessStatus();
        $setUniqueEntityId = $accesser->setPrivate($this, '_uniqueEntityId', Entity::class);
        $getEntityCounter = $accesser->getStaticPrivate(Entity::class, '_entityCounter');
        $setEntityCounter = $accesser->setStaticPrivate(Entity::class, '_entityCounter');

        $cloned = clone $entity;
        $cloned->_behaviors = null;
        $current = $getEntityCounter();
        $setEntityCounter($current + 1);
        $setUniqueEntityId($current);
        $seen[$this->getUniqueEntityId()] = $cloned;

        foreach($cloned->_relations as &$relation)
        {
            $relation = $this->svSpamCloneEntity($relation, $seen);
        }

        return $cloned;
    }

    protected function getHasLoginRecords(): bool
    {
        $login = $this->svLastLogin;

        return $login !== null;
    }

    protected function getHasMultiAccounts(): bool
    {
        $finder = LogFinder::finder()
                           ->asActive()
                           ->byUser($this)
                           ->limit(1);

        return (bool)$finder->fetchColumns(['user_id']);
    }

    protected function getHasRegistrationLogs(): bool
    {
        $finder = UserRegistrationLogFinder::finder()
                                           ->forUser($this)
                                           ->limit(1);

        return (bool)$finder->fetchColumns(['user_id']);
    }

    public function getUserRegistrationLogs(): AbstractCollection
    {
        if (empty($this->_relations['UserRegistrationLogs']))
        {
            return new ArrayCollection([]);
        }

        return $this->_relations['UserRegistrationLogs'];
    }

    public function svInitGuestUser($userId, array $kvp = [])
    {
        if ($this->user_id)
        {
            throw new \LogicException('Existing user id must be 0');
        }

        $kvp['user_id'] = $userId;
        foreach($kvp as $key => $value)
        {
            $this->_values[$key] = $value;
        }
    }

    public function rejectUser($reason = '', ?\XF\Entity\User $byUser = null)
    {
        // pre-XF2.1.6 support
        if (\XF::$versionId < 2010670 && !empty(Globals::$shimRejectUser))
        {
            $reason = (string)$reason;
            $reason = \XF::app()->stringFormatter()->wholeWordTrim($reason, 200);
            $this->error($reason);
            $this->user_state = 'rejected';
            $this->app()->spam()->userChecker()->logSpamTrigger('user_rejected', null);
        }

        return parent::rejectUser($reason, $byUser);
    }

    public function canApproveEmailDomain(): bool
    {
        return $this->hasPermission('general', 'svSignup_approve_email');
    }

    public function canBanEmailDomain(): bool
    {
        return $this->hasPermission('general', 'svSignup_ban_email');
    }

    public function canBanAsn(): bool
    {
        return $this->hasPermission('general', 'svSignup_ban_asn');
    }

    public function hasBannableASN($viaGetter = true): array
    {
        $userRegistrationLogs = $this->UserRegistrationLogs;
        if (!$userRegistrationLogs->count())
        {
            if (!$viaGetter)
            {
                /** @var AbstractCollection $userRegistrationLogs */
                $userRegistrationLogs = $this->getRelation('UserRegistrationLogs');
            }

            if (!$userRegistrationLogs->count())
            {
                return [];
            }
        }

        // todo better handling of multiple signup records, ie from user merging. shouldn't be a major issue for most signups
        foreach ($userRegistrationLogs->reverse() AS $userRegistrationLog)
        {
            foreach ($userRegistrationLog->details_ AS $detail)
            {
                $phraseName = $detail['phrase'] ?? '';
                if ($phraseName === 'sv_reg_log.as_ok' || $phraseName ===  'sv_reg_log.as_fail')
                {
                    $data = $detail['data'] ?? [];
                    if (\count($data) !== 0)
                    {
                        // we do real checking in \SV\SignupAbuseBlocking\XF\ApprovalQueue\User
                        return $data;
                    }
                }
            }
        }

        return [];
    }

    /**
     * @param string|null $email
     * @return bool|string
     */
    public function isEmailDomainKnownForSvSignupAbuseBlocking(?string $email = null)
    {
        $email = $email ?? $this->email;
        if (\strlen($email) === 0)
        {
            return true;
        }

        $bannedEmails = $this->app()->container('bannedEmails');

        /** @var EmailValidator $emailValidator */
        $emailValidator = $this->app()->validator('Email');
        $emailValidator->setOptions([
            'check_typos' => true, // avoid changing email we set
            'banned' => $bannedEmails,
        ]);

        if (\XF::isAddOnActive('SV/EmailDomainValidation'))
        {
            // avoid DNS lookups which are slow when using
            $emailValidator->setOption('dns_validate', false);
        }

        if (!$emailValidator->isValid($email)) // value really is an email
        {
            return true;
        }

        return AllowEmailDomainRepo::get()->valueMatchesFromCache($email);
    }

    /**
     * @param string $email
     * @return bool
     */
    protected function verifyEmail(&$email)
    {
        $isValidEmail = parent::verifyEmail($email);

        if ($this->isUpdate() && $email === $this->getExistingValue('email'))
        {
            return $isValidEmail;
        }

        if ($this->getOption('admin_edit') && $email === '')
        {
            return $isValidEmail;
        }

        $allowedEmailDomain = $this->isEmailDomainKnownForSvSignupAbuseBlocking($email);
        if (\is_string($allowedEmailDomain))
        {
            AllowEmailDomainRepo::get()->triggerItemUsage($allowedEmailDomain);
        }

        return $isValidEmail;
    }

    public function setUserRejected($reason = '', ?\XF\Entity\User $byUser = null)
    {
        // pre-XF 2.2.4 fix
        $reason = (string)$reason;
        if (\XF::$versionId < 2020470)
        {
            $reason = \XF::app()->stringFormatter()->wholeWordTrim($reason, 200);
        }

        $shimRejectedUser = Globals::$shimRejectUser ?? false;
        if ($shimRejectedUser && \XF::$versionId >= 2010670)
        {
            $this->error($reason);
            $this->user_state = 'rejected';
            $this->app()->spam()->userChecker()->logSpamTrigger('user_rejected', null);
        }

        return parent::setUserRejected($reason, $byUser);
    }

    public function getUserStatePhrase()
    {
        if (is_callable([parent::class, 'getUserStatePhrase']))
        {
            /** @noinspection PhpUndefinedMethodInspection */
            return parent::getUserStatePhrase();
        }

        switch ($this->user_state)
        {
            case 'valid':
                return \XF::phrase('valid');
            case 'email_confirm':
                return \XF::phrase('email_confirm');
            case 'email_confirm_edit':
                return \XF::phrase('email_confirm_edit');
            case 'email_bounce':
                return \XF::phrase('email_bounce');
            case 'moderated':
                return \XF::phrase('moderated');
            case 'rejected':
                return \XF::phrase('rejected');
            case 'disabled':
                return \XF::phrase('disabled');
            default:
                return $this->user_state;
        }
    }

    public function getSvLastLogin(): ?UserLoginLog
    {
        return UserLoginLogFinder::finder()
                                 ->byUser($this)
                                 ->order('log_date', 'desc')
                                 ->limit(1)
                                 ->fetchOne();
    }

    public function setSvLastLogin(?UserLoginLog $userLoginLog): void
    {
        $this->_getterCache['svLastLogin'] = $userLoginLog;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['AccountDetectionToken'] = ['getter' => 'getAccountDetectionToken', 'cache' => false];
        $structure->getters['HasLoginRecords'] = ['getter' => 'getHasLoginRecords', 'cache' => true];
        $structure->getters['HasMultiAccounts'] = ['getter' => 'getHasMultiAccounts', 'cache' => true];
        $structure->getters['HasRegistrationLogs'] = ['getter' => 'getHasRegistrationLogs', 'cache' => true];
        $structure->getters['UserRegistrationLogs'] = ['getter' => 'getUserRegistrationLogs', 'cache' => false];
        $structure->getters['svLastLogin'] = ['getter' => 'getSvLastLogin', 'cache' => true];

        $structure->relations['AccountDetectionToken'] = [
            'entity'     => 'SV\SignupAbuseBlocking:Token',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['user_id', '=', '$user_id'], ['active', '=', 1]
            ]
        ];
        $structure->relations['UserRegistrationLogs'] = [
            'entity' => 'SV\SignupAbuseBlocking:UserRegistrationLog',
            'type' => self::TO_MANY,
            'conditions' => 'user_id',
            'key' => 'user_registration_log_id',
            'order' => ['log_date', 'DESC'],
        ];
        $structure->relations['MultiAccountUser'] = [
            'entity'     => 'SV\SignupAbuseBlocking:MultiAccountUser',
            'type'       => self::TO_ONE,
            'conditions' => 'user_id',
            'primary'    => true
        ];

        $structure->options['svMultiAccountLogEvent'] = false;

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

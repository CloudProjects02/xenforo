<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\User as UserRepo;

/**
 * COLUMNS
 *
 * @property int|null          $log_id
 * @property int                      $event_id
 * @property int                      $user_id
 * @property string                   $username
 * @property int                      $detection_date
 * @property array                    $detection_methods
 * @property int|null                 $token_id
 * @property bool                     $is_alertable
 * @property bool                     $active
 * GETTERS
 * @property-read array|Phrase        $DetectionMethods
 * @property-read ExtendedUserEntity  $User
 * RELATIONS
 * @property-read ?ExtendedUserEntity $User_
 * @property-read ?Token              $Token
 * @property-read ?LogEvent           $LogEvent
 */
class Log extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    public function shouldIgnore(ExtendedUserEntity $userFromPreviousLog): bool
    {
        if (!$this->detection_methods || !\is_array($this->detection_methods))
        {
            return false;
        }
        $keys = \array_keys($this->detection_methods);
        foreach ($keys as $key)
        {
            if ($userFromPreviousLog->canBypassMultipleAccountDetection($key))
            {
                return true;
            }
        }

        return false;
    }

    public function canChangeAlerting(): bool
    {
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return false;
        }

        if (!$this->User)
        {
            return false;
        }

        return $visitor->hasPermission('multipleAccountHandling', 'logAlerting');
    }

    /**
     * @return array|Phrase
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function getDetectionMethods()
    {
        if (!empty($this->detection_methods))
        {
            $phrasedDetectionMethods = [];
            foreach ($this->detection_methods AS $detectionMethod => $detectionValue)
            {
                $phrasedDetectionMethods[] = \XF::phrase('sv_multiple_account_method.' . $detectionMethod, $detectionValue);
            }

            return $phrasedDetectionMethods;
        }

        return [];
    }

    protected function _preSave()
    {
        parent::_preSave();
        $this->detection_methods = array_unique($this->detection_methods, SORT_REGULAR);
    }

    protected function _postSave()
    {
        parent::_postSave();
        $changes = false;
        if ($this->isUpdate())
        {
            if ($this->isChanged('active'))
            {
                if ($this->active)
                {
                    $this->LogEvent->logMadeActive($this);
                }
                else
                {
                    $this->LogEvent->logMadeInactive($this);
                }
                $changes = true;
            }
        }
        else if ($this->LogEvent->exists() && $this->isInsert())
        {
            if ($this->active)
            {
                $this->LogEvent->logAdded($this);
                $changes = true;
            }
        }

        if ($changes)
        {
            $this->LogEvent->saveIfChanged($saved, true, false);
            $this->LogEvent->ReportData->saveIfChanged($saved, true, false);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();
        throw new \LogicException('No Supported');
    }

    protected function getUser(): UserEntity
    {
        $user = $this->User_;

        if (!$user)
        {
            $username = $this->username;
            if (!$username)
            {
                $username = 'user id:' . $this->user_id;
            }
            /** @var ExtendedUserEntity $user */
            $user = Helper::repository(UserRepo::class)->getGuestUser($username);
            $user->svInitGuestUser(0, [
                'is_banned' => true,
            ]);
        }

        return $user;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_multiple_account_log';
        $structure->shortName = 'SV\SignupAbuseBlocking:Log';
        $structure->primaryKey = 'log_id';
        $structure->columns = [
            'log_id'            => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'event_id'          => ['type' => self::UINT, 'required' => true],
            'user_id'           => ['type' => self::UINT, 'required' => true],
            'username'          => ['type' => self::STR,  'maxLength' => 50, 'required' => true],
            'detection_date'    => ['type' => self::UINT, 'default' => \XF::$time],
            'detection_methods' => ['type' => self::JSON_ARRAY, 'default' => []],
            'token_id'          => ['type' => self::UINT, 'required' => true, 'nullable' => true, 'default' => null],
            'is_alertable'      => ['type' => self::BOOL, 'default' => true],
            'active'            => ['type' => self::BOOL, 'default' => true]
        ];
        $structure->getters = [
            'DetectionMethods' => ['getter' => 'getDetectionMethods', 'cache' => true],
            'User'             => ['getter' => 'getUser', 'cache' => true],
        ];
        $structure->relations = [
            'User'     => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'Token'    => [
                'entity'     => 'SV\SignupAbuseBlocking:Token',
                'type'       => self::TO_ONE,
                'conditions' => 'token_id',
                'primary'    => true
            ],
            'LogEvent' => [
                'entity'     => 'SV\SignupAbuseBlocking:LogEvent',
                'type'       => self::TO_ONE,
                'conditions' => 'event_id',
                'primary'    => true
            ],
        ];


        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

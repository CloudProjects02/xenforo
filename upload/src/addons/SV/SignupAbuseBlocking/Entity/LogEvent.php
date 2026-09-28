<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\Repository\User as UserRepo;

/**
 * COLUMNS
 *
 * @property int|null                     $event_id
 * @property int                          $detection_date
 * @property int                          $report_data_id
 * @property int                          $triggering_user_id
 * @property string                       $username
 * @property int                          $log_count
 * @property string                       $detection_action
 * GETTERS
 * @property-read ExtendedUserEntity      $User
 * @property-read string|Phrase           $DetectionAction
 * RELATIONS
 * @property-read ?ExtendedUserEntity     $User_
 * @property-read ?ReportData             $ReportData
 * @property-read AbstractCollection<Log> $Logs
 */
class LogEvent extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    public function clearCascadeSaveHack()
    {
        $this->_cascadeSave = [];
    }

    public function createLog(array $detectionMethods, ?ExtendedUserEntity $user = null, ?Token $token = null, ?int $userId = null, ?string $username = null): Log
    {
        $userId = $user->user_id ?? ($userId ?: 0);
        $username = $user->username ?? ($username ?: "Guest ({$userId})");
        if (!$detectionMethods)
        {
            \XF::logError('Unexpected lack of detection records:'.$userId . '-' . $username );
        }

        $methods = [];
        foreach ($detectionMethods as $detectionMethod => $detectionData)
        {
            $methods[$detectionMethod] = $detectionData->forPhraseData();
        }

        $logEntity = Log::create();
        $logEntity->event_id = $this->exists() ? $this->event_id : $this->_getDeferredValue(function () { return $this->event_id; }, 'save');
        $logEntity->detection_methods = $methods;
        $logEntity->token_id = $token ? $token->token_id : null;

        if ($user)
        {
            $logEntity->user_id = $user->user_id;
            $logEntity->username = $user->username;
            if (!$user->Profile->multiple_account_detection_alertable)
            {
                $logEntity->is_alertable = false;
                $logEntity->active = false;
            }
        }
        else
        {
            $logEntity->user_id = $userId;
            $logEntity->username = $username;
        }

        $logEntity->hydrateRelation('LogEvent', $this);
        $logEntity->hydrateRelation('Token', $token);
        $logEntity->hydrateRelation('User', $user);

        $this->addCascadedSave($logEntity);

        return $logEntity;
    }

    public function logAdded(Log $log): void
    {
        $this->log_count++;
        $reportData = $this->ReportData;
        if ($reportData !== null)
        {
            $reportData->logAdded($log);
        }
    }

    public function logMadeActive(Log $log): void
    {
        $this->log_count++;
        $reportData = $this->ReportData;
        if ($reportData !== null)
        {
            $reportData->logMadeActive($log);
        }
    }

    public function logMadeInactive(Log $log): void
    {
        $this->log_count--;
        $reportData = $this->ReportData;
        if ($reportData !== null)
        {
            $reportData->logMadeInactive($log);
        }
    }

    protected function _postSave(): void
    {
        parent::_postSave();
        if ($this->isInsert())
        {
            $logCount = \intval($this->db()->fetchOne('
                SELECT COUNT(*)
                FROM xf_sv_multiple_account_log
                WHERE event_id = ? AND active = 1
            ', [$this->event_id]));

            $this->fastUpdate('log_count', $logCount);
        }

        $reportData = $this->ReportData;
        if ($reportData !== null && $this->isChanged('report_data_id'))
        {
            $reportData->eventMadeActive($this);
            $reportData->saveIfChanged($saved, true, false);
        }
    }

    protected function _postDelete(): void
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
                $username = 'user id:' . $this->triggering_user_id;
            }
            /** @var ExtendedUserEntity $user */
            $user = Helper::repository(UserRepo::class)->getGuestUser($username);
            $user->svInitGuestUser(0, [
                'is_banned' => true,
            ]);
        }

        return $user;
    }

    protected function getDetectionAction(): Phrase
    {
        return \XF::phrase('sv_multiple_account_action.' . $this->detection_action);
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_multiple_account_event';
        $structure->shortName = 'SV\SignupAbuseBlocking:LogEvent';
        $structure->primaryKey = 'event_id';
        $structure->columns = [
            'event_id'           => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'detection_date'     => ['type' => self::UINT, 'default' => \XF::$time],
            'report_data_id'     => ['type' => self::UINT, 'required' => false, 'nullable' => true],
            'triggering_user_id' => ['type' => self::UINT, 'required' => true],
            'username'           => ['type' => self::STR,  'maxLength' => 50, 'required' => true],
            'log_count'          => ['type' => self::UINT, 'default' => 0],
            'detection_action'   => ['type' => self::STR, 'required' => true],
        ];
        $structure->relations = [
            'User'       => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [['user_id', '=', '$triggering_user_id']],
                'primary'    => true
            ],
            'ReportData' => [
                'entity'     => 'SV\SignupAbuseBlocking:ReportData',
                'type'       => self::TO_ONE,
                'conditions' => 'report_data_id',
                'primary'    => true
            ],
            'Logs'       => [
                'entity'     => 'SV\SignupAbuseBlocking:Log',
                'type'       => self::TO_MANY,
                'conditions' => 'event_id',
                'primary'    => true
            ],
        ];

        $structure->getters = [
            'User'             => ['getter' => 'getUser', 'cache' => true],
            'DetectionAction'  => ['getter' => 'getDetectionAction', 'cache' => true],
        ];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

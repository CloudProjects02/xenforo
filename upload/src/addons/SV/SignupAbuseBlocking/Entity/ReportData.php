<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\Finder\Log as LogFinder;
use SV\SignupAbuseBlocking\XF\Entity\Report as ExtendedReportEntity;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 *
 * @property int|null                                $report_data_id
 * @property int|null                                $report_id
 * @property int                                     $log_count
 * @property bool                                    $active
 * RELATIONS
 * @property-read ?ExtendedReportEntity              $Report
 * @property-read AbstractCollection<LogEvent>       $Events
 * @property-read AbstractCollection<ReportDataUser> $Users
 */
class ReportData extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    public function countUniqueAccounts(): int
    {
        return $this->getRelationFinder('ReportDataUsers')->total();
    }

    public function rebuildUsers(bool $rebuildMultiAccountSummary = true): void
    {
        $reportDataId = $this->report_data_id;

        $db = $this->db();
        $db->query('DROP TEMPORARY TABLE IF EXISTS xf_sv_multiple_account_report_data_user_rebuild_pending');
        $db->query('CREATE TEMPORARY TABLE xf_sv_multiple_account_report_data_user_rebuild_pending (
            `user_id` int(10) UNSIGNED NOT NULL,
            `first_seen_date` int(10) UNSIGNED NOT NULL,
            `last_seen_date` int(10) UNSIGNED NOT NULL,
            `count` int(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`user_id`)
        )');
        if ($rebuildMultiAccountSummary)
        {
            $db->query('DROP TEMPORARY TABLE IF EXISTS xf_sv_multiple_account_user_rebuild_pending');
            $db->query('CREATE TEMPORARY TABLE xf_sv_multiple_account_user_rebuild_pending (
                `user_id` int(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`user_id`)
            )');
        }

        try
        {
            $db->beginTransaction();

            if ($rebuildMultiAccountSummary)
            {
                $db->query('
                INSERT IGNORE INTO xf_sv_multiple_account_user_rebuild_pending (user_id)
                    SELECT user_id 
                    FROM xf_sv_multiple_account_report_data_user 
                    WHERE report_data_id = ? AND user_id != 0
                ', [$reportDataId]);
            }

            $db->query('
                INSERT IGNORE INTO xf_sv_multiple_account_report_data_user_rebuild_pending (user_id, first_seen_date, last_seen_date, count)
                SELECT user_id, MIN(detection_date), MAX(detection_date), COUNT(detection_date)
                FROM (
                    SELECT log.user_id, event.detection_date
                    FROM xf_sv_multiple_account_event AS event
                    JOIN xf_sv_multiple_account_log AS log ON log.event_id = event.event_id
                    JOIN xf_sv_multiple_account_report_data as reportData on reportData.report_data_id = event.report_data_id
                    WHERE event.report_data_id = ? AND log.active = 1 AND reportData.active = 1 AND log.user_id != 0
                    UNION ALL
                    SELECT triggering_user_id AS user_id, event.detection_date
                    FROM xf_sv_multiple_account_event AS event
                    JOIN xf_sv_multiple_account_log AS log ON log.event_id = event.event_id
                    JOIN xf_sv_multiple_account_report_data as reportData on reportData.report_data_id = event.report_data_id
                    WHERE event.report_data_id = ? AND log.active = 1 AND reportData.active = 1 AND triggering_user_id != 0
                ) a
                GROUP BY user_id

            ', [$reportDataId, $reportDataId]);

            if ($rebuildMultiAccountSummary)
            {
                $db->query('
                    INSERT IGNORE INTO xf_sv_multiple_account_user_rebuild_pending (user_id)
                    SELECT user_id 
                    FROM xf_sv_multiple_account_report_data_user_rebuild_pending 
                ');
            }

            $db->query('
                DELETE reportDataUser            
                FROM xf_sv_multiple_account_report_data_user AS reportDataUser
                LEFT JOIN xf_sv_multiple_account_report_data_user_rebuild_pending AS pendingRebuild ON pendingRebuild.user_id = reportDataUser.user_id
                WHERE reportDataUser.report_data_id = ? AND pendingRebuild.user_id IS NULL
            ', [$reportDataId]);

            $db->query(' INSERT IGNORE INTO xf_sv_multiple_account_report_data_user (report_data_id, user_id, first_seen_date, last_seen_date, count)
                SELECT ?, user_id, first_seen_date, last_seen_date, count
                FROM xf_sv_multiple_account_report_data_user_rebuild_pending
            ', [$reportDataId]);

            if ($rebuildMultiAccountSummary)
            {
                $db->query('
                    DELETE multiAccountUser            
                    FROM xf_sv_multiple_account_user AS multiAccountUser
                    JOIN xf_sv_multiple_account_user_rebuild_pending AS pendingRebuild ON pendingRebuild.user_id = multiAccountUser.user_id
                ');

                $db->query('
                    INSERT INTO xf_sv_multiple_account_user (user_id, first_seen_date, last_seen_date, count)
                    SELECT user_id, MIN(detection_date), MAX(detection_date), COUNT(user_id)
                    FROM (
                        SELECT log.user_id, event.detection_date
                        FROM xf_sv_multiple_account_event AS event
                        JOIN xf_sv_multiple_account_log AS log ON log.event_id = event.event_id
                        JOIN xf_sv_multiple_account_report_data AS reportData ON reportData.report_data_id = event.report_data_id
                        JOIN xf_sv_multiple_account_user_rebuild_pending AS pendingRebuild ON pendingRebuild.user_id = log.user_id
                        WHERE log.active = 1 AND reportData.active = 1 AND log.user_id != 0
                        UNION ALL
                        SELECT event.triggering_user_id AS user_id, event.detection_date
                        FROM xf_sv_multiple_account_event AS event
                        JOIN xf_sv_multiple_account_report_data AS reportData ON reportData.report_data_id = event.report_data_id
                        JOIN xf_sv_multiple_account_user_rebuild_pending AS pendingRebuild ON pendingRebuild.user_id = event.triggering_user_id
                        JOIN xf_sv_multiple_account_log AS log ON log.event_id = event.event_id
                        WHERE log.active = 1 AND reportData.active = 1 AND event.triggering_user_id != 0
                    ) a
                    GROUP BY a.user_id
                    ON DUPLICATE KEY UPDATE 
                        count = VALUES(count),
                        first_seen_date = VALUES(first_seen_date), 
                        last_seen_date = VALUES(last_seen_date)                                   
                ');
            }

            $db->commit();
        }
        finally
        {
            $db->query('DROP TABLE IF EXISTS xf_sv_multiple_account_report_data_user_rebuild_pending');
            if ($rebuildMultiAccountSummary)
            {
                $db->query('DROP TABLE IF EXISTS xf_sv_multiple_account_user_rebuild_pending');
            }
        }
    }

    protected function addUserToReport(UserEntity $user, int $date): void
    {
        $db = $this->db();

        $db->query('
            INSERT INTO xf_sv_multiple_account_report_data_user (report_data_id, user_id, first_seen_date, last_seen_date, count)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE 
                first_seen_date = LEAST(first_seen_date, VALUES(first_seen_date)), 
                last_seen_date = GREATEST(last_seen_date, VALUES(last_seen_date)),
                count = count + 1
        ', [$this->report_data_id, $user->user_id, $date, $date, 1]);

        $db->query('
            INSERT INTO xf_sv_multiple_account_user (user_id, first_seen_date, last_seen_date, count)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE 
                first_seen_date = LEAST(first_seen_date, VALUES(first_seen_date)), 
                last_seen_date = GREATEST(last_seen_date, VALUES(last_seen_date)),
                count = count + 1
        ', [$user->user_id, $date, $date, 1]);
    }

    public function getGroupedLogs(): array
    {
        $finder = LogFinder::finder()
                           ->inReportData($this->report_data_id)
                           ->asActive();
        $logs = $finder->fetch();
        $finder->loadUserRecords($logs);

        $logsByUser = [];
        $users = [];

        /** @var Log|false $log */
        $log = $logs->first();
        if ($log)
        {
            $primaryUser = $log->LogEvent->User;
            $userId = $log->LogEvent->triggering_user_id ?: 'guest-' . $log->LogEvent->username;
            $users[$userId] = $primaryUser;
            $logsByUser[$userId]['Event'][$log->LogEvent->event_id] = $log->LogEvent;
        }
        else
        {
            $primaryUser = null;
        }

        foreach ($logs as $log)
        {
            $token = $log->LogEvent->triggering_user_id ?: 'guest-' . $log->LogEvent->username;
            $users[$token] = $log->LogEvent->User;
            if (!$primaryUser)
            {
                $primaryUser = $log->LogEvent->User;
            }

            $userId = $log->user_id ?: 'guest-' . $log->username;
            $users[$userId] = $log->User;

            if (!isset($logsByUser[$userId]['Event']))
            {
                $logsByUser[$userId]['Event'] = [];
            }
            $logsByUser[$userId]['Event'][$log->LogEvent->event_id] = $log->LogEvent;

            if (!isset($logsByUser[$userId]['UserLog']))
            {
                $logsByUser[$userId]['UserLog'] = [];
            }
            $logsByUser[$userId]['UserLog'][$log->log_id] = $log;
        }

        return [
            'reportDataId'  => $this->report_data_id,
            'primaryUser'   => $primaryUser,
            'primaryUserId' => $primaryUser ? $primaryUser->user_id : null,
            'users'         => $users,
            'logsByUser'    => $logsByUser,
        ];
    }

    protected function addLogUsers(Log $log): void
    {
        if (!$this->active)
        {
            return;
        }

        if ($log->LogEvent === null)
        {
            // wat
            return;
        }

        if ($log->User !== null)
        {
            $this->addUserToReport($log->User, $log->detection_date);
        }
        if ($log->LogEvent->User !== null)
        {
            $this->addUserToReport($log->LogEvent->User, $log->detection_date);
        }
    }

    public function logAdded(Log $log): void
    {
        $this->log_count++;
        $this->addLogUsers($log);
    }

    public function logMadeActive(Log $log): void
    {
        $this->log_count++;
        $this->addLogUsers($log);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function logMadeInactive(Log $log): void
    {
        $this->log_count--;
        $this->rebuildUsers();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function logRemoved(Log $log): void
    {
        $this->log_count--;
        $this->rebuildUsers();
    }

    public function eventMadeActive(LogEvent $event): void
    {
        if (!$this->active)
        {
            return;
        }

        $this->log_count += $event->Logs->count();

        $this->addUserToReport($event->User, $event->detection_date);
        foreach ($event->Logs as $log)
        {
            $this->addUserToReport($log->User, $log->detection_date);
        }
    }

    protected function _preSave(): void
    {
        if (!$this->active)
        {
            $this->active = null;
        }
    }

    protected function _postSave(): void
    {
        parent::_postSave();
        if ($this->isUpdate() && $this->isChanged('active') && $this->active)
        {
            $this->rebuildUsers();
        }
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_multiple_account_report_data';
        $structure->shortName = 'SV\SignupAbuseBlocking:ReportData';
        $structure->primaryKey = 'report_data_id';
        $structure->columns = [
            'report_data_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'report_id'      => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'log_count'      => ['type' => self::UINT, 'default' => 0],
            'active'         => ['type' => self::BOOL, 'nullable' => true, 'default' => true],
        ];

        $structure->relations = [
            'Report'          => [
                'entity'     => 'XF:Report',
                'type'       => self::TO_ONE,
                'conditions' => 'report_id',
                'primary'    => true
            ],
            'Events'          => [
                'entity'     => 'XF:Report',
                'type'       => self::TO_MANY,
                'conditions' => 'report_data_id',
                'primary'    => true
            ],
            'ReportDataUsers' => [
                'entity'     => 'SV\SignupAbuseBlocking:ReportDataUser',
                'type'       => self::TO_MANY,
                'conditions' => 'report_data_id',
            ],
        ];

        $structure->defaultWith = [];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

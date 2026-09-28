<?php
/**
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\Repository;

use SV\SignupAbuseBlocking\Entity\Log;
use SV\SignupAbuseBlocking\Finder\MultiAccountUser as MultiAccountUserFinder;
use SV\SignupAbuseBlocking\Finder\Token as TokenFinder;
use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethod;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodEmailLink;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodLegacy;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodUserSwitch;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionRecord;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodCookie;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethodIp;
use SV\SignupAbuseBlocking\Entity\LogEvent as LogEventEntity;
use SV\SignupAbuseBlocking\Entity\ReportData as ReportDataEntity;
use SV\SignupAbuseBlocking\Entity\Token as TokenEntity;
use SV\SignupAbuseBlocking\XF\Entity\Report as ExtendedReportEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Db\DuplicateKeyException;
use XF\Entity\Report as ReportEntity;
use XF\Entity\ReportComment as ReportCommentEntity;
use XF\Entity\Thread as ThreadEntity;
use XF\Entity\User as UserEntity;
use XF\Finder\User as UserFinder;
use XF\Finder\Report as ReportFinder;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
use XF\Phrase;
use XF\PrintableException;
use XF\Service\Report\Commenter as ReportCommenterService;
use XF\Service\Report\Creator as ReportCreator;
use SV\SignupAbuseBlocking\Util\Ip as Ip;
use LogicException;
use function array_key_exists;
use function count;

class MultipleAccount extends Repository
{
    public const MATCHING_MODE_OR = 0;
    public const MATCHING_MODE_AND = 1;
    public const MATCHING_MODE_COOKIE_ONLY = 2;

    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function postRegistrationMultipleAccountDetection(UserEntity $currentUser, array $dataCollection): void
    {
        try
        {
            $this->processMultipleAccountDetection($currentUser, $dataCollection, 'register');
        }
        catch (\Throwable $e)
        {
            // do not block registration if any sort of error occurs
            \XF::logException($e, true);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
        }
    }

    public function getUserForReportCreation(): ?ExtendedUserEntity
    {
        $userId = $this->options()->svSockSignupCheckReportingUser ?? 1;
        if ($userId === 0)
        {
            return null;
        }

        $reportCreator = Helper::find(UserEntity::class, $userId);
        if ($reportCreator === null)
        {
            $e = new \LogicException("[SignupAbuseBlocking] 'Multi-account report user' has an invalid userId. Aborting reporting multiple accounts.");
            if (\XF::$developmentMode)
            {
                throw $e;
            }
            \XF::logException($e, false, '', true);
        }

        /** @var ExtendedUserEntity $reportCreator */
        return $reportCreator;
    }

    /**
     * @param UserEntity        $currentUser
     * @param DetectionRecord[] $dataCollection
     * @param string            $detectionAction
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function processMultipleAccountDetection(UserEntity $currentUser, array $dataCollection, $detectionAction)
    {
        if (count($dataCollection) === 0)
        {
            return;
        }

        /** @var ExtendedUserEntity $currentUser */
        $userId = (int)$currentUser->user_id;
        if ($userId === 0)
        {
            // wat
            return;
        }

        // record the detection events
        $dataCollectionToReport = [];

        $logEvent = LogEventEntity::create();
        $logEvent->detection_action = $detectionAction;
        $logEvent->triggering_user_id = $userId;
        $logEvent->username = $currentUser->username;
        $logEvent->hydrateRelation('User', $currentUser);
        $logEvent->report_data_id = null;

        foreach ($dataCollection AS $data)
        {
            $user = $data->user;

            $data->log = $logEvent->createLog($data->methods, $user, $data->token);

            if ($user->Profile->multiple_account_detection_alertable)
            {
                $dataCollectionToReport[$user->user_id] = $data;
            }
        }

        $db = $this->db();
        $userIdsQuoted = $db->quote(\array_unique([$userId] + array_keys($dataCollectionToReport)));
        if (\strlen($userIdsQuoted) === 0)
        {
            // wat
            return;
        }

        $db->beginTransaction();

        // need some locking to avoid wonky race conditions
        $db->query("select user_id from xf_user where user_id in ({$userIdsQuoted}) order by user_id for update");

        $logEvent->hydrateRelation('Logs', new ArrayCollection($dataCollectionToReport));
        $logEvent->save(true, false);
        $logEvent->clearCascadeSaveHack();

        // if we don't have at least a pair of users, there is nothing to REPORT
        // but we record matching records anyway
        if (count($dataCollectionToReport) === 0 || !$currentUser->Profile->multiple_account_detection_alertable)
        {
            $db->commit();

            return;
        }

        // Find the first reported event for the reportable user id set
        // don't need the token, as detectMultipleAccount should have loaded it IF it was alertable
        $reportDataId = (int)$db->fetchOne("
            select distinct `event`.report_data_id
            from xf_sv_multiple_account_log as `log`
            join xf_sv_multiple_account_event as `event` on `event`.event_id = `log`.event_id
            join xf_sv_multiple_account_report_data as `reportData` on (`reportData`.report_data_id = `event`.report_data_id and `reportData`.`active` = 1)
            join xf_user_profile as `userProfile` on `userProfile`.user_id = `log`.user_id
            where (`log`.user_id in ({$userIdsQuoted}) or `event`.triggering_user_id in ({$userIdsQuoted}))
                  AND `log`.active = 1
                  AND `reportData`.active = 1
            order by `event`.detection_date, `event`.triggering_user_id, `log`.user_id
            limit 1
        ");

        // burn the log event <-> report data link
        if ($reportDataId === 0)
        {
            $reportData = ReportDataEntity::create();
            $reportData->save(true, false);
            $reportDataId = $reportData->report_data_id;
        }
        $logEvent->report_data_id = $reportDataId;
        $logEvent->saveIfChanged($saved, true, false);
        $reportData = $logEvent->ReportData;

        $db->commit();

        // determine if any of the users have been marked as not-alertable
        $userReportingTotals = $db->fetchAllKeyed("
            select user_id, count(log_id) as `activeLogs`
            from xf_sv_multiple_account_log as `log`
            join xf_sv_multiple_account_event as `event` on `event`.event_id = `log`.event_id
            where (`log`.user_id in ({$userIdsQuoted}) or `event`.triggering_user_id in ({$userIdsQuoted}))
                  AND `log`.active = 1
                  AND `log`.is_alertable = 1
                  AND `event`.report_data_id = ? 
                  AND `log`.event_id <> ?
            group by user_id
            having `activeLogs` > 0
        ", 'user_id', [$logEvent->report_data_id, $logEvent->event_id]);

        foreach ($dataCollectionToReport as $userId => $data)
        {
            if (isset($userReportingTotals[$userId]) && $userReportingTotals[$userId]['activeLogs'] == 0)
            {
                unset($dataCollectionToReport[$userId]);
            }
        }

        // no log entries to report
        if (count($dataCollectionToReport) === 0)
        {
            return;
        }

        $reportCreator = $this->getUserForReportCreation();

        $createdReporting = [];
        $logEvent->User->setOption('svMultiAccountLogEvent', $logEvent);
        if ($reportCreator !== null)
        {
            \XF::asVisitor($reportCreator, function () use (&$createdReporting, $logEvent) {
                $createdReporting  = $this->createNewReportEntries($logEvent);
            });
        }
        try
        {
            $reportData->saveIfChanged($saved);
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (DuplicateKeyException $e)
        {
            // race condition; something else is active. make sure we save this record.
            $reportData->set('active', null, ['forceSet' => true]);
            $reportData->save();
            // do not bump
            return;
        }

        // determine if this event has recently been seen before to avoid superfluous duplication
        $bumpDedupeFilter = \XF::options()->svSockDedupeFilter ?? [];
        if ($logEvent->log_count === 1 && !empty($bumpDedupeFilter['seenFilter']))
        {
            $cutOff = (int)($bumpDedupeFilter['cutOff'] ?? 0);
            if ($cutOff)
            {
                $cutOff = \XF::$time - $cutOff * 86400;
            }
            if ($this->seenPair($logEvent, $cutOff))
            {
                return;
            }
        }

        $reportCreator = $this->getUserForReportCreation();
        if ($reportCreator !== null)
        {
            \XF::asVisitor($reportCreator, function () use ($createdReporting, $logEvent, $reportData) {
                $this->bumpReportEntries($createdReporting, $logEvent);
                $reportData->saveIfChanged();
            });
        }
    }

    /**
     * @param LogEventEntity $logEvent
     * @param int      $cutoff
     * @return bool
     */
    protected function seenPair(LogEventEntity $logEvent, $cutoff)
    {
        /** @var Log $firstLog */
        $firstLog = $logEvent->Logs->first();

        return (bool)$this->db()->fetchOne('
            select distinct `event`.report_data_id
            from xf_sv_multiple_account_log as `log`
            join xf_sv_multiple_account_event as `event` on `event`.event_id = `log`.event_id
            join xf_sv_multiple_account_report_data as `reportData` on (`reportData`.report_data_id = `event`.report_data_id and `reportData`.`active` = 1)
            join xf_user_profile as `userProfile` on `userProfile`.user_id = `log`.user_id
            where 
                  `log`.active = 1 AND 
                  `event`.event_id <> ? AND 
                  `event`.detection_date > ? AND
                  ((`event`.triggering_user_id = ? and `log`.user_id = ?) or (`event`.triggering_user_id = ? and `log`.user_id = ?))
            order by `event`.detection_date ASC, `event`.triggering_user_id ASC, `log`.user_id ASC
            limit 1
        ', [$logEvent->event_id, $cutoff, $logEvent->triggering_user_id, $firstLog->user_id, $firstLog->user_id, $logEvent->triggering_user_id]);
    }

    /**
     * @param LogEventEntity $logEvent
     * @return array
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function createNewReportEntries(LogEventEntity $logEvent)
    {
        $reportData = $logEvent->ReportData;
        $options = \XF::options();
        $created = [];
        if (!$reportData->Report && ($options->svSockSignupCheckReport ?? false))
        {
            /** @var ExtendedReportEntity $report */
            $report = $this->reportCreator($logEvent);
            if ($report)
            {
                if ($report instanceof ReportEntity)
                {
                    if (!$reportData->exists())
                    {
                        // report may be a new report, or an existing one. Rebind as required
                        $this->db()->query('update xf_sv_multiple_account_report_data set active = 0 where report_id = ? and active = 1', $report->report_id);
                    }
                    $reportData->report_id = $report->report_id;
                    $reportData->hydrateRelation('Report', $report);
                }
                $created['report'] = $report;
            }
        }

        return $created;
    }

    /**
     * @param LogEventEntity $logEvent
     * @return null|ExtendedReportEntity|ThreadEntity
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function reportCreator(LogEventEntity $logEvent): ?Entity
    {
        try
        {
            $creator = Helper::service(ReportCreator::class, 'multiple_account', $logEvent->User);
            $message = $this->buildMessageForReport($logEvent);
            if ($message === null)
            {
                return null;
            }
            $creator->setMessage($message);

            if ($creator->validate($errors))
            {
                /** @var Entity $reportOrThread */
                try
                {
                    $reportOrThread = $creator->save();
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch (DuplicateKeyException $e)
                {
                    $reportOrThread = Helper::finder(ReportFinder::class)
                                             ->where('content_type', '=', 'multiple_account')
                                             ->where('content_id', '=', $logEvent->triggering_user_id)
                                             ->fetchOne();
                }
                if ($reportOrThread === null)
                {
                    if (\XF::$developmentMode)
                    {
                        \XF::logError('Unexpected null return result from ' . ReportCreator::class);
                    }
                    return null;
                }
                \XF::runLater(function() use ($reportOrThread, $creator) {
                    if ($reportOrThread->exists())
                    {
                        $creator->sendNotifications();
                    }
                });

                /** @noinspection PhpStatementHasEmptyBodyInspection */
                if ($reportOrThread instanceof ThreadEntity)
                {
                    // nothing to-do
                }
                else
                {
                    /** @noinspection PhpStatementHasEmptyBodyInspection */
                    if ($reportOrThread instanceof ReportEntity)
                    {
                        // nothing to-do
                    }
                    else if (\XF::$developmentMode)
                    {
                        throw new LogicException('Unexpected reportable content type ' . get_class($reportOrThread) . ' returned from' . ReportCreator::class);
                    }
                    else
                    {
                        return null;
                    }
                }

                return $reportOrThread;
            }
            else
            {
                \XF::logException(new PrintableException($errors), false, 'Failed to create multi-account report:');
            }

            return null;
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, true);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
        }

        return null;
    }

    /**
     * @param LogEventEntity $logEvent
     * @return string|Phrase
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function buildMessageForReport(LogEventEntity $logEvent)
    {
        $bbCode = '[multi_account_block]' . \json_encode([
                'event'         => $logEvent->event_id
            ]) . '[/multi_account_block]';

        if (\XF::options()->svSockSignupIncludeRawInfo ?? false)
        {
            $bbCode .= "\n" . \trim(\XF::app()->templater()->renderMacro('public:sv_multiple_account_macros', 'renderBbCode', [
                'event'         => $logEvent,
                'primaryUser'   => $logEvent->User,
                'primaryUserId' => $logEvent->triggering_user_id,
            ]));
        }

        return $bbCode;
    }

    public function postLoginMultiAccountCheck(UserEntity $user, ?string $detectionAction = null): void
    {
        $detectionAction = $detectionAction ?? 'login';
        try
        {
            $dataCollection = \XF::asVisitor($user, function () use ($user) {
                $receivedToken =  $this->getCookieValue('login');
                /** @var ExtendedUserEntity $user */
                return $this->detectMultipleAccounts($user, $receivedToken);
            });

            $this->processMultipleAccountDetection($user, $dataCollection, $detectionAction);
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
     * @deprecated
     * @noinspection PhpUnusedParameterInspection
     */
    protected function hasIgnoredUserSetForReportEvent(LogEventEntity $logEvent, $reportDataId)
    {
        return false;
    }

    /**
     * @param string $type
     * @param array $a
     * @param array $b
     * @return bool
     */
    protected function compareDetectionMethod($type, $a, $b)
    {
        switch($type)
        {
            case 'ip':
                if (!isset($a['ip']) || !isset($b['ip']))
                {
                    return false;
                }
                $ip1 = \XF\Util\Ip::convertIpStringToBinary($a['ip']);
                $ip2 = \XF\Util\Ip::convertIpStringToBinary($b['ip']);
                return  $ip1 && $ip2 && $ip1 === $ip2;
            case 'cookie':
                if (!isset($a['expected']) || !isset($a['received']))
                {
                    return false;
                }
                if (!isset($b['expected']) || !isset($b['received']))
                {
                    return false;
                }
                return $a['expected'] === $b['expected'] && $a['received'] === $b['received'] ||
                       $a['expected'] === $b['received'] && $a['received'] === $b['expected'];
            default:
                return false;
        }
    }

    /**
     * @param int $reportDataId
     * @param int $skipEvent
     * @return int[]
     */
    protected function getExistingUsers($reportDataId, $skipEvent)
    {
        return \XF::db()->fetchAllColumn('
            SELECT DISTINCT `event`.triggering_user_id AS `user_id`
            FROM xf_sv_multiple_account_event AS `event`
            WHERE `event`.report_data_id = ? AND `event`.event_id <> ?
            UNION
            SELECT DISTINCT `log`.user_id 
            FROM xf_sv_multiple_account_event AS `event`
            JOIN xf_sv_multiple_account_log AS `log` ON `log`.event_id = `event`.event_id -- and `log`.active = 1
            WHERE `event`.report_data_id = ? AND `event`.event_id <> ?
        ', [$reportDataId, $skipEvent, $reportDataId, $skipEvent]);
    }

    protected function bumpReportEntries($justCreated, LogEventEntity $logEvent)
    {
        $reportData = $logEvent->ReportData;
        $options = \XF::options();
        $forceThreadBump = (bool)($options->reportIntoForumId ?? 0);
        if (empty($justCreated['report']) && $reportData->Report && ($options->svSockSignupCheckReport ?? false) && !$forceThreadBump)
        {
            $reason = null;
            $reportState = $reportData->Report->report_state;
            // check the report state filter
            $sendDuplicate = $options->svSockSignupCheckDupeReports ?? [];
            $canBump = $sendDuplicate[$reportState] ?? false;
            if ($canBump)
            {
                $reason = \XF::phrase('sv_multi_account_report_reopen.report_state', ['state' => $reportState]);
            }
            if (!$canBump)
            {
                // if the last report state doesn't match the last assigned state, just bump anyway...
                $lastStateChange = \XF::db()->fetchOne(" 
                    SELECT state_change 
                    FROM xf_report_comment
                    WHERE report_id = ? AND state_change NOT IN ('','moved')
                    ORDER BY comment_date DESC
                    LIMIT 1
                ", $reportData->report_id);
                if ($lastStateChange && $lastStateChange != $reportState)
                {
                    $canBump = !empty($sendDuplicate[$lastStateChange]);
                    if ($canBump)
                    {
                        $reason = \XF::phrase('sv_multi_account_report_reopen.report_state_last', ['state' => $lastStateChange]);
                    }
                }
            }
            if (!$canBump)
            {
                // check if this is a multi-account event with new users
                $userIds = $this->getExistingUsers($logEvent->ReportData->report_data_id, $logEvent->event_id);
                $userIds = \array_fill_keys($userIds, true);
                if (empty($userIds[$logEvent->triggering_user_id]))
                {
                    $canBump = true;
                    $reason = \XF::phrase('sv_multi_account_report_reopen.unknown_user', ['userId' => $logEvent->triggering_user_id]);
                }
                else
                {
                    foreach ($logEvent->Logs as $log)
                    {
                        if (empty($userIds[$log->user_id]))
                        {
                            $canBump = true;
                            $reason = \XF::phrase('sv_multi_account_report_reopen.unknown_user', ['userId' => $log->user_id]);
                            break;
                        }
                    }
                }
            }
            if ($canBump)
            {
                $this->reportCommentCreator($reportData->Report, $logEvent, $reason);
            }
        }
    }

    protected function reportCommentCreator(ExtendedReportEntity $report, LogEventEntity $logEvent, ?Phrase $bumpReason): ?ReportCommentEntity
    {
        try
        {
            $creator = Helper::service(ReportCommenterService::class, $report);
            $message = $this->buildMessageForReport($logEvent);
            if ($message === null)
            {
                return null;
            }
            if ($bumpReason && (\XF::options()->svLogReportReopenReason ?? false))
            {
                $message = $message . "\n" . $bumpReason->render('raw');
            }
            $creator->setMessage($message);
            // make sure the report_data_id is included
            $contentInfo = $report->content_info;
            $contentInfo['report_data_id'] = $logEvent->report_data_id;
            $report->content_info = $contentInfo;
            if ($creator->validate($errors))
            {
                /** @var ReportCommentEntity $reportComment */
                $reportComment = $creator->save();

                \XF::runLater(function() use ($reportComment, $creator) {
                    if ($reportComment->exists())
                    {
                        $creator->sendNotifications();
                    }
                });

                return $reportComment;
            }
            else
            {
                \XF::logException(new PrintableException($errors), false, 'Failed to create multi-account report:');
            }

            return null;
        }
        catch (\Throwable $e)
        {
            \XF::logException($e, true);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
        }

        return null;
    }

    protected function addDetectionMethod(array &$dataCollection, ExtendedUserEntity $user, DetectionMethod $method): DetectionRecord
    {
        $userId = (int)$user->user_id;

        /** @var ?DetectionRecord $detectionRecord */
        $detectionRecord = $dataCollection[$userId] ?? null;
        if ($detectionRecord === null)
        {
            $dataCollection[$userId] = $detectionRecord = new DetectionRecord($user, [
                $method->method => $method,
            ]);

            return $detectionRecord;
        }

        $existingMethod = $detectionRecord->methods[$method->method] ?? null;
        if ($existingMethod !== null)
        {
            $existingMethod->combine($method);
        }
        else
        {
            $detectionRecord->methods[$method->method] = $method;
        }

        return $detectionRecord;
    }

    /**
     * Detects multiple accounts for the $currentUser, $currentUser MAY NOT be fully constructed!
     *
     * @param ExtendedUserEntity        $currentUser
     * @param string|null $receivedToken
     * @return DetectionRecord[]
     */
    public function detectMultipleAccounts(ExtendedUserEntity $currentUser, $receivedToken)
    {
        $currentUserId = $currentUser->user_id;
        /** @var DetectionRecord[] $dataCollection */
        $dataCollection = [];

        $expectedToken = $currentUser->AccountDetectionToken->token;
        /** @var TokenEntity $tokenEntity */
        $tokenEntity = $receivedToken ? $this->getTokenFromCookie($receivedToken) : null;

        if ($tokenEntity && $receivedToken !== $expectedToken)
        {
            /** @var ExtendedUserEntity $tokenOwner */
            $tokenOwner = $tokenEntity->User;

            if ($tokenOwner !== null)
            {
                $userId = $tokenOwner->user_id;
                // avoid reporting a multi-account event if the token is just being upgraded
                if ($userId && $userId !== $currentUserId)
                {
                    $cookieDetection = new DetectionMethodCookie($expectedToken ?? '', $receivedToken ?? '');
                    if (!$tokenOwner->canBypassMultipleAccountDetection($cookieDetection->method))
                    {
                        $detectionRec = $this->addDetectionMethod($dataCollection, $tokenOwner, $cookieDetection);
                        $detectionRec->token = $tokenEntity;
                    }
                }
            }
            else
            {
                // trigger setting a new cookie as the old account was deleted
                $receivedToken = null;
            }
        }

        $oldUser = Globals::$userBeforeLogin ?? null;
        if ($oldUser !== null)
        {
            $oldUserId = (int)$oldUser->user_id;
            if ($oldUserId !== 0 && $currentUserId !== $oldUserId)
            {
                $detectionMethod = Globals::$userBeforeLoginType ?? new DetectionMethodUserSwitch();
                $this->addDetectionMethod($dataCollection, $oldUser, $detectionMethod);
            }
        }

        if (!$receivedToken || $expectedToken !== $receivedToken)
        {
            $this->setCookieValue($expectedToken);
        }

        if (count($dataCollection) === 0)
        {
            return $dataCollection;
        }

        // ip match only matter if another signal is detected
        $ipAddress = $this->app()->request()->getIp();
        $this->detectIpMatchesForExisting($currentUserId, $dataCollection, $ipAddress);

        return $dataCollection;
    }

    /**
     * @param int|null               $currentUserId
     * @param array<DetectionRecord> $dataCollection
     * @param string                 $ipAddress
     * @return void
     */
    public function detectIpMatchesForExisting(?int $currentUserId, array &$dataCollection, string $ipAddress): void
    {
        $ipOption = \XF::options()->svSockSignupCheckMatchingIps ?? [];
        if (!($ipOption['checkIp'] ?? true))
        {
            return;
        }
        $minTime = (int)($ipOption['minTime'] ?? 15);
        if ($minTime <= 0)
        {
            return;
        }

        // gets all users which match the IP
        // skip the current user as it will have logged usage of this IP by this point
        // note; this is extended by internal add-ons
        /** @var array<int,ExtendedUserEntity> $userByIP */
        $userByIP = $this->getUsersWithIp($ipAddress, $minTime);
        $currentUserId = (int)$currentUserId;
        unset($userByIP[$currentUserId]);
        if (count($userByIP) === 0)
        {
            return;
        }

        $userIds = [];
        foreach ($dataCollection as $rec)
        {
            $userId = (int)$rec->user->user_id;
            $userIds[$userId] = $userId;
        }

        $ipDetection = new DetectionMethodIp($ipAddress);

        foreach ($userByIP as $userId => $user)
        {
            if ($user->canBypassMultipleAccountDetection($ipDetection->method))
            {
                continue;
            }

            if ($userId !== 0 && array_key_exists($userId, $userIds))
            {
                $this->addDetectionMethod($dataCollection, $user, $ipDetection);
            }
        }
    }

    public function getTokenFromCookie(string $cookie): ?TokenEntity
    {
        return TokenFinder::finder()
                          ->where('token', '=', $cookie)
                          ->order('active', 'desc')
                          ->with(['User', 'User.Profile'], false)
                          ->fetchOne();
    }

    /**
     * @param string|null $ip
     * @return bool
     */
    protected function isIpAddressAllowed($ip = null)
    {
        if ($ip === null)
        {
            $ip = $this->app()->request()->getIp();
        }
        if (!$ip)
        {
            return false;
        }

        $raw = $this->options()->svSockSignupCheckIpAllowedList ?? '';
        if (\strlen($raw) === 0)
        {
            return false;
        }
        $allowedIps = array_filter(array_map('trim', explode(',', preg_replace('/\s+/', ',', $raw))));
        if (empty($allowedIps))
        {
            return false;
        }

        $binaryIp = \XF\Util\Ip::convertIpStringToBinary($ip);
        if (!$binaryIp)
        {
            return false;
        }

        foreach ($allowedIps as $allowedIp)
        {
            $results = \XF\Util\Ip::parseIpRangeString($allowedIp);
            if ($results && Ip::ipMatchesRange($binaryIp, $results['startRange'], $results['endRange']))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Extended by SV/IpUsageSummary, do not change the signature!
     * @param string $ip
     * @param int    $timeLimit
     * @return array<ExtendedUserEntity|Entity>
     */
    public function getUsersWithIp($ip, $timeLimit)
    {
        if (!$ip)
        {
            return [];
        }
        if ($timeLimit <= 0)
        {
            return [];
        }

        $ip = \XF\Util\Ip::convertIpStringToBinary($ip);
        if (!$ip)
        {
            return [];
        }

        $userIds = $this->db()->fetchAllColumn('
            SELECT user_id
            FROM xf_ip AS ip
            WHERE ip.ip = ? AND log_date >= ?
            GROUP BY ip.user_id
        ', [$ip, \XF::$time - $timeLimit * 60]);

        return Helper::finder(UserFinder::class)
                  ->with('PermissionCombination')
                  ->whereIds($userIds)
                  ->with('Profile')
                  ->fetch()
                  ->toArray();
    }

    /**
     * @param ExtendedUserEntity $user
     * @return null|TokenEntity
     */
    public function getTokenForUser(ExtendedUserEntity $user)
    {
        if (!$user->user_id)
        {
            return null;
        }

        return $user->AccountDetectionToken;
    }

    public function getCookieValue(?string $detectionAction): ?string
    {
        $options = \XF::options();

        $cookieName = $options->svSockSignupAccountCookie ?? '';
        if (\strlen($cookieName) === 0)
        {
            return null;
        }
        // explicitly use $_COOKIE/setcookie super-global to bypass XF cookie handling
        $cookieValue = $_COOKIE[$cookieName] ?? '';
        // detect known-bad value and zap it
        if ($cookieValue)
        {
            if (\strpos($cookieValue, 'SV\SignupAbuseBlocking:Token[') === 0)
            {
                setcookie($cookieName, '', \XF::$time - 3600, '', '');
                setcookie($cookieName, '', \XF::$time - 3600, '/', '');
                $_COOKIE[$cookieName] = '';
            }
            else if (\preg_match_all('#' . preg_quote($cookieName) . '=SV%5CSignupAbuseBlocking%3AToken%5B;?#', $_SERVER['HTTP_COOKIE'] ?? '', $matches) && $matches)
            {
                setcookie($cookieName, '', \XF::$time - 3600, '/', '');
            }
        }
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();

        $cookieFormat = $options->svSockSignupCookieFormat ?? [];
        if (!empty($cookieFormat['legacy1']) || !empty($cookieFormat['legacy2']))
        {
            if (\strpos($cookieValue, ',') !== false || is_numeric($cookieValue))
            {
                $reportDataId = null;
                $cookieValue = $this->handleLegacyCookieValue($visitor, $cookieValue, $detectionAction, !empty($cookieFormat['legacy2']), $reportDataId);
                $this->setCookieValue($cookieValue);
            }
        }

        return $cookieValue;
    }

    public function detectedCrossAccountPerAccountEmailLink(UserEntity $visitor, UserEntity $user, string $detectionAction, ?string $ipAddress): void
    {
        $visitorUserId = (int)$visitor->user_id;
        $contentUserId = (int)$user->user_id;
        if ($visitorUserId !== 0 && $contentUserId !== 0 && $visitorUserId !== $contentUserId)
        {
            $this->handleSharedEmailLink($visitor, $user, $detectionAction, $ipAddress);
        }
    }

    /**
     * @param ExtendedUserEntity|UserEntity $visitor
     * @param ExtendedUserEntity|UserEntity $user
     * @param string                  $detectionAction
     * @param string|null             $ipAddress
     * @return void
     * @throws \Throwable
     */
    public function handleSharedEmailLink(UserEntity $visitor, UserEntity $user, string $detectionAction, ?string $ipAddress): void
    {
        try
        {
            $dataCollection = [];
            $this->addDetectionMethod($dataCollection, $user, new DetectionMethodEmailLink());
            if ($ipAddress !== null)
            {
                $this->detectIpMatchesForExisting($visitor->user_id, $dataCollection, $ipAddress);
            }
            $this->processMultipleAccountDetection($visitor, $dataCollection, $detectionAction);
        }
        catch (\Throwable $e)
        {
            // do not block action if any sort of error occurs
            \XF::logException($e, true);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
        }
    }

    /**
     * @param ExtendedUserEntity|UserEntity $visitor
     * @param string|string[]      $cookieValue
     * @param string               $detectionAction
     * @param bool                 $allowMultiple
     * @param int|null             $reportDataId
     * @return string
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function handleLegacyCookieValue(UserEntity $visitor, $cookieValue, string $detectionAction, bool $allowMultiple, ?int &$reportDataId): string
    {
        if (\is_array($cookieValue))
        {
            $userIds = $cookieValue;
            $cookieValue = implode(',', $cookieValue);
        }
        else if ($allowMultiple && \strpos($cookieValue, ',') !== false)
        {
            $userIds = \explode(',', $cookieValue);
            $userIds = \array_map('\intval', $userIds);
            $userIds = \array_unique($userIds, SORT_NUMERIC);
            $userIds = \array_fill_keys($userIds, null);
        }
        else
        {
            $userIds = [(int)$cookieValue => null];
        }
        unset($userIds[0]);
        unset($userIds[$visitor->user_id]);
        $detectionMethods = ['legacy' => new DetectionMethodLegacy($cookieValue ?? '')];

        $token = null;

        if ($userIds)
        {
            $users = Helper::finder(UserFinder::class)
                          ->whereIds(\array_keys($userIds))
                          ->fetch()->toArray();

            $db = $this->db();
            $db->beginTransaction();

            // require a mostly empty $reportData for later multiple account detection events to find this data

            $reportData = Helper::find(ReportDataEntity::class, $reportDataId);
            if (!$reportData)
            {
                $reportData = ReportDataEntity::create();
                $reportData->save(true, false);
                $reportDataId = $reportData->report_data_id;
            }

            $logEvent = LogEventEntity::create();
            $logEvent->detection_action = $detectionAction;
            $logEvent->triggering_user_id = $visitor->user_id;
            $logEvent->username = $visitor->username;
            $logEvent->hydrateRelation('User', $visitor);
            $logEvent->report_data_id = $reportData->report_data_id;

            foreach ($userIds AS $userId => $username)
            {
                /** @var ExtendedUserEntity $user */
                $user = $users[$userId] ?? null;
                if ($user)
                {
                    $username =  $user->username;
                }
                else
                {
                    $username = $username ?: ''. $userId;
                }
                if ($user)
                {
                    // ensure this user has a token
                    $userToken = $user->AccountDetectionToken;
                    if (empty($token))
                    {
                        $token = $userToken;
                    }
                }

                $logEvent->createLog($detectionMethods, $user, $token, $userId, $username);
            }

            $logEvent->save(true, false);
            $reportData->saveIfChanged($saved, true, false);

            $db->commit();
        }

        if (!$token)
        {
            /** @var TokenEntity $token */
            $token = $visitor->AccountDetectionToken;
        }

        return $token->token;
    }

    /**
     * Sets the multiple account cookie value.
     *
     * @param TokenEntity|string|null $value The cookie. Falsy to remove cookie.
     * @param int            $time  int How long the cookie is valid for, in seconds.
     */
    public function setCookieValue($value, $time = null)
    {
        $options = \XF::options();
        if (!($options->svSockSignupCheckCookie ?? false))
        {
            return;
        }

        // this will be done at the end of the registration request, to avoid over-stamping the value due to rejection
        $duringRegistration = Globals::$duringRegistration ?? false;
        if ($duringRegistration)
        {
            return;
        }
        if ($value instanceof TokenEntity)
        {
            $value = $value->token;
        }

        if ($time === null)
        {
            $time = ($this->options()->svSockSignupAccountCookieLifeSpan ?? 24) * 2592000;
        }

        $cookieName = $this->options()->svSockSignupAccountCookie ?? '';
        if (\strlen($cookieName) === 0)
        {
            return;
        }
        $expire = !$value ? \XF::$time - 3600 : \XF::$time + $time;
        // explicitly use $_COOKIE/setcookie super-global to bypass XF cookie handling, changing this will break cookie path magic!
        // path = "", domain = "", matches to the current domain + current path (or any sub-path)
        \setcookie($cookieName, $value, $expire, '', '');//, false, false);
        // ensure calling setCookieValue => getCookieValue, works as expected
        $_COOKIE[$cookieName] = $value;
    }

    public function getFinderForList(): MultiAccountUserFinder
    {
        return MultiAccountUserFinder::finder()
                                     ->with('User', true);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\Log as LogEntity;
use SV\SignupAbuseBlocking\Entity\ReportData as ReportDataEntity;
use SV\StandardLib\Helper;
use XF\Entity\UserOption as UserOptionEntity;
use XF\Entity\UserPrivacy as UserPrivacyEntity;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Finder\UserOption as UserOptionFinder;
use XF\Finder\UserPrivacy as UserPrivacyFinder;
use XF\Finder\User as UserFinder;
use XF\Repository\User as UserRepo;

/**
 * @method AbstractCollection<LogEntity>|LogEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method LogEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,LogEntity>
 * @extends Finder<LogEntity>
 */
class Log extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }

    /**
     * @param int|UserEntity $userId
     * @return self
     */
    public function asAlterUser($userId): self
    {
        if ($userId instanceof UserEntity)
        {
            $userId = $userId->user_id;
        }

        $this->where('user_id', '=', $userId);

        return $this;
    }

    /**
     * @param int|UserEntity $userId
     * @return self
     */
    public function byUser($userId): self
    {
        if ($userId instanceof UserEntity)
        {
            $userId = $userId->user_id;
        }

        $this->whereOr(['user_id', '=', $userId], ['LogEvent.triggering_user_id', '=', $userId]);

        return $this;
    }

    /**
     * @param bool $alertable
     * @return self
     */
    public function asAlertable(bool $alertable = true): self
    {
        $this->where('is_alertable', '=', $alertable);

        return $this;
    }

    /**
     * @param bool $active
     * @return self
     */
    public function asActive(bool $active = true): self
    {
        $this->where('active', '=', $active);

        return $this;
    }

    /**
     * @param int $reportDataId
     * @return self
     */
    public function inReportData(int $reportDataId): self
    {
        $this->with(['LogEvent'], true);

        $this->where('LogEvent.report_data_id', '=', $reportDataId);

        $this->order('LogEvent.detection_date', 'ASC');
        $this->order('LogEvent.triggering_user_id', 'ASC');
        $this->order('user_id', 'ASC');

        return $this;
    }

    public function loadUserRecords(AbstractCollection $logs)
    {
        $userIds = [];
        $userOptions = [];
        $userPrivacy = [];
        $reportDataIds = [];
        /** @var LogEntity $log */
        foreach ($logs as $log)
        {
            $userId = $log->user_id;
            if ($userId)
            {
                if (!Helper::findCached(UserEntity::class, $userId))
                {
                    $userIds[$userId] = true;
                }
                else
                {
                    if (!Helper::findCached(UserOptionEntity::class, $userId))
                    {
                        $userOptions[$userId] = true;
                    }
                    if (!Helper::findCached(UserPrivacyEntity::class, $userId))
                    {
                        $userPrivacy[$userId] = true;
                    }
                }
            }

            $logEvent = $log->LogEvent;
            if ($logEvent === null)
            {
                continue;
            }

            $userId = $logEvent->triggering_user_id;
            if ($userId && !Helper::findCached(UserEntity::class, $userId))
            {
                $userIds[$userId] = true;
            }

            $reportDataId = $logEvent->report_data_id;
            if ($reportDataId && !Helper::findCached(ReportDataEntity::class, $reportDataId))
            {
                $reportDataIds[$reportDataId] = true;
            }
        }

        if ($userIds)
        {
            Helper::finder(UserFinder::class)->with(['Profile', 'Privacy'])->whereIds(\array_keys($userIds))->fetch();
        }
        if ($userOptions)
        {
            Helper::finder(UserOptionFinder::class)->whereIds(\array_keys($userOptions))->fetch();
        }
        if ($userPrivacy)
        {
            Helper::finder(UserPrivacyFinder::class)->whereIds(\array_keys($userPrivacy))->fetch();
        }
        if ($reportDataIds)
        {
            ReportData::finder()->whereIds(\array_keys($reportDataIds))->fetch();
        }

        $permCache = [];
        // hydrate nulls..
        /** @var LogEntity $log */
        foreach ($logs as $log)
        {
            $userId = $log->user_id;
            if (!$userId || !Helper::findCached(UserEntity::class, $userId))
            {
                $log->hydrateRelation('User', null);
            }
            if ($log->User !== null)
            {
                $permCache[$log->User->permission_combination_id] = true;
            }

            $logEvent = $log->LogEvent;
            if ($logEvent !== null)
            {
                $userId = $logEvent->triggering_user_id;
                if (!$userId || !Helper::findCached(UserEntity::class, $userId))
                {
                    $logEvent->hydrateRelation('User', null);
                }
                if ($logEvent->User !== null)
                {
                    $permCache[$logEvent->User->permission_combination_id] = true;
                }
            }
        }

        if ($permCache)
        {
            $userRepo = Helper::repository(UserRepo::class);
            if (\is_callable([$userRepo, 'preloadGlobalPermissionsFromIds']))
            {
                $userRepo->preloadGlobalPermissionsFromIds(array_keys($permCache));
            }
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

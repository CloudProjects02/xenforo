<?php

namespace SV\SignupAbuseBlocking\ControllerPlugin;

use SV\SignupAbuseBlocking\Entity\Log as LogEntity;
use SV\SignupAbuseBlocking\Entity\LogEvent as LogEventEntity;
use SV\SignupAbuseBlocking\Finder\Log as LogFinder;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Reply\AbstractReply;
use XF\Util\Arr;
use function array_merge;
use function count;

/**
 * Class MultipleAccount
 *
 * @package SV\SignupAbuseBlocking\ControllerPlugin
 */
class MultipleAccount extends AbstractPlugin
{
    public function actionMultipleAccountEventList(?UserEntity $user, string $pagePath, string $viewName, array $additionalLinkParams = [], $noFilterContainer = null): AbstractReply
    {
        $finder = LogFinder::finder()
                           ->asActive();
        if ($user !== null)
        {
            $finder->byUser($user);
        }

        $linkParams = [];
        $reportDataParams = [];
        if (!empty($additionalLinkParams['report_data_id']))
        {
            $reportDataId = $additionalLinkParams['report_data_id'];

            $reportDataParams['report_data_id'] = $reportDataId;
            $linkParams['report_data_id'] = $reportDataId;
            unset($additionalLinkParams['report_data_id']);

            $finder->inReportData($reportDataId);
        }
        else
        {
            $finder->order('detection_date', 'desc');
        }

        $total = $finder->total();
        $page = $this->filterPage($additionalLinkParams['page'] ?? 0);
        unset($additionalLinkParams['page']);
        $perPage = 40;

        $userId = (int)$this->filter('user_id', 'uint');
        if ($userId !== 0)
        {
            $finder->whereOr(['user_id', $userId], ['LogEvent.triggering_user_id', $userId]);
        }

        $originalUsersStr = $this->filter('users', 'str');

        $usersArr = Arr::stringToArray($originalUsersStr, '/\s*,\s*/');
        if (count($usersArr) !== 0)
        {
            $linkParams['users'] = $originalUsersStr;
            foreach ($usersArr as &$username)
            {
                $username = '%' . $username . '%';
            }
            $finder->whereOr(['User.username', 'like', $usersArr], ['LogEvent.username', 'like', $usersArr]);
        }

        $originalEmailsStr = $this->filter('emails', 'str');
        $emailsArr = Arr::stringToArray($originalEmailsStr, '/\s*,\s*/');
        if (count($emailsArr) !== 0)
        {
            $linkParams['emails'] = $originalEmailsStr;
            foreach ($emailsArr as &$email)
            {
                $email = '%' . $email . '%';
            }
            $finder->where('User.email', 'like', $emailsArr);
        }

        $logs = $finder->limitByPage($page, $perPage)->fetch();
        $finder->loadUserRecords($logs);

        // collect logs by event
        /** @var LogEventEntity [] $events */
        $events = [];
        $logsByEvents = [];
        /** @var LogEntity $log */
        foreach($logs as $log)
        {
            if ($log->LogEvent === null)
            {
                continue;
            }

            if (empty($events[$log->event_id]))
            {
                $events[$log->event_id] = clone $log->LogEvent;
            }
            $logsByEvents[$log->event_id][] = $log;
        }
        foreach($logsByEvents as $eventId => $logs)
        {
            $events[$eventId]->hydrateRelation('Logs', new ArrayCollection($logs));
        }

        $viewParams = [
            'filterUrl' => $this->buildLink($pagePath, $user, $reportDataParams),
            'paginationUrl' => $pagePath,

            'user' => $user,
            'events' => $events,

            'users' => $originalUsersStr,
            'emails' => $originalEmailsStr,

            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'linkParams' => array_merge($linkParams, $additionalLinkParams),

            'noFilterContainer' => $noFilterContainer,
        ];

        return $this->view($viewName, 'public:sv_multiple_account_list', $viewParams);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Content;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\ContentCheckerInterface;

abstract class AbstractUserRegistrationLogCheck extends AbstractProvider implements ContentCheckerInterface
{
    abstract protected function getDefaultAction(): string;
    abstract protected function resolveData(UserEntity $user);
    abstract protected function isEmpty(UserRegistrationLogEntity $logEntry): bool;
    abstract protected function isMatching(UserRegistrationLogEntity $logEntry, $data): bool;
    abstract protected function onNonMatch(UserRegistrationLogEntity $logEntry, $data, string $defaultAction): string;

    public function check(UserEntity $user, $message, array $extraParams = []): void
    {
        // default
        $this->logDecision('allowed');
        if (!$user->user_id)
        {
            return;
        }

        $actionOnNotMatch = $this->getDefaultAction();
        if ($actionOnNotMatch === 'allowed')
        {
            return;
        }

        /** @var ExtendedUserEntity $user */
        $userRegLogs = $user->getRelation('UserRegistrationLogs');
        if ($userRegLogs === null || $userRegLogs->count() === 0)
        {
            return;
        }

        $data = $this->resolveData($user);
        if (!$data)
        {
            return;
        }

        $noMatches = true;
        $firstNonMatch = null;
        foreach($userRegLogs as $userRegLog)
        {
            if ($this->isEmpty($userRegLog))
            {
                continue;
            }
            else if ($this->isMatching($userRegLog, $data))
            {
                $noMatches = false;
                break;
            }
            else if ($firstNonMatch === null)
            {
                $firstNonMatch = $userRegLog;
            }
        }

        if ($noMatches && $firstNonMatch !== null)
        {
            $action = $this->onNonMatch($firstNonMatch, $data, $actionOnNotMatch);
            $this->logDecision($action);
        }
    }

    public function submitSpam($contentType, $contentIds)
    {
    }

    public function submitHam($contentType, $contentIds)
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

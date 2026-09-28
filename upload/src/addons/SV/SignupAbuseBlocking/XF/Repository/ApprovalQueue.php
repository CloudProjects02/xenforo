<?php

namespace SV\SignupAbuseBlocking\XF\Repository;

use SV\SignupAbuseBlocking\Finder\UserRegistrationLog as UserRegistrationLogFinder;
use XF\Entity\ApprovalQueue as ApprovalQueueEntity;
use XF\Mvc\Entity\ArrayCollection;

/**
 * Class ApprovalQueue
 *
 * @package SV\SignupAbuseBlocking\XF\Repository
 */
class ApprovalQueue extends XFCP_ApprovalQueue
{
    public function addContentToUnapprovedItems($unapprovedItems)
    {
        parent::addContentToUnapprovedItems($unapprovedItems);

        $userIds = [];

        /** @var ApprovalQueueEntity $unapprovedItem */
        foreach ($unapprovedItems AS $unapprovedItem)
        {
            if ($unapprovedItem->content_type === 'user')
            {
                $userIds[$unapprovedItem->content_id] = $unapprovedItem;
            }
        }

        if ($userIds)
        {
            $userRegistrationLogFinder = UserRegistrationLogFinder::finder()
                                                                  ->forUser(\array_keys($userIds));
            $userRegLogs = $userRegistrationLogFinder->fetch();
            $groupedUserRegLogs = $userRegLogs->groupBy('user_id');

            foreach ($userIds AS $userId => $unapprovedItem)
            {
                if (empty($groupedUserRegLogs[$userId]) || !$unapprovedItem->Content)
                {
                    continue;
                }

                $unapprovedItem->Content->hydrateRelation('UserRegistrationLogs', new ArrayCollection($groupedUserRegLogs[$userId]));
            }
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

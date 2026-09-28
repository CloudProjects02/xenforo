<?php

namespace SV\SignupAbuseBlocking\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @Extends \XF\Entity\Forum
 *
 * @property-read bool $is_per_day_rate_limited
 */
class Forum extends XFCP_Forum
{
    public function canCreateThread(&$error = null)
    {
        $canCreate = parent::canCreateThread($error);

        if ($canCreate && ($this->is_per_day_rate_limited ?? false))
        {
            $perDayLimit = (int)\XF::visitor()->hasNodePermission($this->node_id, 'svSignup_limitRepliesDay');
            $error = \XF::phrase('svSignupAbuseBlocking_limited.limitRepliesDay', ['perDayLimit' => $perDayLimit]);
            return false;
        }

        return $canCreate;
    }

    protected function isPerDayRateLimited(): bool
    {
        $visitor = \XF::visitor();
        $userId = (int)$visitor->user_id;
        if ($userId === 0)
        {
            return false;
        }

        $nodeId = $this->node_id;

        if ($visitor->hasNodePermission($nodeId, 'manageAnyThread'))
        {
            return false;
        }

        $perDayLimit = (int)$visitor->hasNodePermission($nodeId, 'svSignup_limitRepliesDay');
        if ($perDayLimit > 0)
        {
            $replyCount = (int)$this->db()->fetchOne('
                SELECT COUNT(post_id)
                FROM xf_post
                WHERE user_id = ? and post_date >= ?
            ', [$userId, \XF::$time - 24*60*60]);

            if ($replyCount >= $perDayLimit)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Structure $structure
     * @return Structure
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['is_per_day_rate_limited'] = ['getter' => 'isPerDayRateLimited', 'cache' => true];
    
        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

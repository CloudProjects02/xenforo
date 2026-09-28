<?php

namespace SV\SignupAbuseBlocking\XF\Entity;

use XF\Phrase;/**
 * @Extends \XF\Entity\Thread
 * @property-read ?Forum $Forum
 */
class Thread extends XFCP_Thread
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function canReply(&$error = null)
    {
        $canReply = parent::canReply($error);

        if ($canReply && ($this->Forum->is_per_day_rate_limited ?? false))
        {
            $perDayLimit = (int)\XF::visitor()->hasNodePermission($this->node_id, 'svSignup_limitRepliesDay');
            $error = \XF::phrase('svSignupAbuseBlocking_limited.limitRepliesDay', ['perDayLimit' => $perDayLimit]);
            return false;
        }

        return $canReply;
    }

    public function getReplyDeniedReason(): ?Phrase
    {
        if ($this->Forum->is_per_day_rate_limited ?? false)
        {
            $perDayLimit = (int)\XF::visitor()->hasNodePermission($this->node_id, 'svSignup_limitRepliesDay');
            return \XF::phrase('svSignupAbuseBlocking_limited.limitRepliesDay', ['perDayLimit' => $perDayLimit]);
        }

        return null;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

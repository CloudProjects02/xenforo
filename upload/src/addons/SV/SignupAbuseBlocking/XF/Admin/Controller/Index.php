<?php

namespace SV\SignupAbuseBlocking\XF\Admin\Controller;

use SV\SignupAbuseBlocking\Finder\SignupThrottlingLog as SignupThrottlingLogFinder;
use XF\Mvc\Reply\View as ViewReply;

/**
 * @extends \XF\Admin\Controller\Index
 */
class Index extends XFCP_Index
{
    public function actionIndex()
    {
        $reply = parent::actionIndex();

        if ($reply instanceof ViewReply && \XF::visitor()->hasAdminPermission('svAntiSpam'))
        {
            if (\XF::options()->svSignupThrottling ?? false)
            {
                $throttlingLogs = SignupThrottlingLogFinder::finder()
                                                           ->forAdminList()
                                                           ->fetch();

                $reply->setParam('hasSvSignupThrottling', true);
                $reply->setParam('svSignupThrottling', $throttlingLogs);
            }
        }

        return $reply;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

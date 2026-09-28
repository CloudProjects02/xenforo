<?php

namespace SV\SignupAbuseBlocking\XF\Service\Thread;



/**
 * @Extends \XF\Service\Thread\Creator
 */
class Creator extends XFCP_Creator
{
    public function checkForSpam()
    {
        $oldState = $this->thread->discussion_state;

        parent::checkForSpam();

        if ($oldState === 'moderated' && (\XF::options()->svSpamCheckModeratedPosts ?? false) && $this->user->isSpamCheckRequired())
        {
            $this->postPreparer->checkForSpam();
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

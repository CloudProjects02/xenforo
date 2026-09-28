<?php

namespace SV\SignupAbuseBlocking\XF\Service\Thread;

/**
 * @Extends \XF\Service\Thread\Replier
 */
class Replier extends XFCP_Replier
{
    public function checkForSpam()
    {
        $oldState = $this->post->message_state;

        parent::checkForSpam();

        if ($oldState === 'moderated' && (\XF::options()->svSpamCheckModeratedPosts ?? false) && $this->user->isSpamCheckRequired())
        {
            $this->postPreparer->checkForSpam();
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

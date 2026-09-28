<?php

namespace SV\SignupAbuseBlocking\XF\Spam;

/**
 * @Extends \XF\Spam\ContentChecker
 */
class ContentChecker extends XFCP_ContentChecker
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function logSpamTrigger($contentType, $contentId)
    {
        // https://xenforo.com/community/threads/logspamtrigger-does-not-match-getfinaldecision-resulting-in-the-wrong-action-type-being-logged.222001/
        // force logSpamTrigger to behave sanely
        $finalDecision = $this->getFinalDecision();

        $oldDecisions = $this->decisions;
        $this->decisions = [$finalDecision];
        try
        {
            return parent::logSpamTrigger($contentType, $contentId);
        }
        finally
        {
            $this->decisions = $oldDecisions;
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

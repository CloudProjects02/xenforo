<?php

namespace SV\SignupAbuseBlocking\XF\BbCode;

/**
 * @Extends \XF\BbCode\RuleSet
 */
class RuleSet extends XFCP_RuleSet
{
    public function addDefaultTags()
    {
        parent::addDefaultTags();

        $this->addTag('multi_account_block', [
            'hasOption'    => false,
            'plain'        => true,
            'stopSmilies'  => true,
            'stopAutoLink' => true
        ]);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

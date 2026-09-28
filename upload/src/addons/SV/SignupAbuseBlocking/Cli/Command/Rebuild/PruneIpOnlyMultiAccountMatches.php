<?php

namespace SV\SignupAbuseBlocking\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class PruneIpOnlyMultiAccountMatches extends AbstractRebuildCommand
{
    protected function getRebuildName(): string
    {
        return 'sv-prune-ip-only-multi-account-matches';
    }

    protected function getRebuildDescription(): string
    {
        return 'Prune multi-account matches which are IP only';
    }

    protected function getRebuildClass(): string
    {
        return \SV\SignupAbuseBlocking\Job\PruneIpOnlyMultiAccountMatches::class;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

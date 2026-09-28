<?php

namespace SV\SignupAbuseBlocking\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildMultiAccountSummary extends AbstractRebuildCommand
{
    protected function getRebuildName(): string
    {
        return 'sv-multi-account-summary';
    }

    protected function getRebuildDescription(): string
    {
        return '';
    }

    protected function getRebuildClass(): string
    {
        return \SV\SignupAbuseBlocking\Job\RebuildMultiAccountSummary::class;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

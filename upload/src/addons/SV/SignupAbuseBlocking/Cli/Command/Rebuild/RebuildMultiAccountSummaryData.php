<?php

namespace SV\SignupAbuseBlocking\Cli\Command\Rebuild;

use SV\SignupAbuseBlocking\Job\LinkUsersToMultiAccountReport as LinkUsersToMultiAccountReportJob;
use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildMultiAccountSummaryData extends AbstractRebuildCommand
{
    protected function getRebuildName(): string
    {
        return 'sv-multi-account-summary-data';
    }

    protected function getRebuildDescription(): string
    {
        return '';
    }

    protected function getRebuildClass(): string
    {
        return LinkUsersToMultiAccountReportJob::class;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

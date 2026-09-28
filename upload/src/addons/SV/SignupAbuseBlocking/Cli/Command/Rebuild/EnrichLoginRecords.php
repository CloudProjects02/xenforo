<?php

namespace SV\SignupAbuseBlocking\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class EnrichLoginRecords extends AbstractRebuildCommand
{
    protected function getRebuildName(): string
    {
        return 'sv-enrich-login-records';
    }

    protected function getRebuildDescription(): string
    {
        return 'Assigns ASN & country to login/registration records. Recommend setting up MaxMind for bulk queries';
    }

    protected function getRebuildClass(): string
    {
        return \SV\SignupAbuseBlocking\Job\EnrichLoginRecords::class;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace MMO\OldThreadWarning;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    use InstallerHelper;

    public function upgrade2020170Step1()
    {
        $this->renameOption('motw_enable_warning', 'motwEnableWarning');
        $this->renameOption('motw_days_cutoff', 'motwDaysCutoff');
    }
}
 		 				  			 					  		 	  	  	 		   	  			 		 					  		 				   		 	  	  	  		 			  		    	 	    	  				  	   		   					 				 		  		

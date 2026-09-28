<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Repository\ChangeLog as ChangeLogRepo;

/**
 * @Extends \XF\Service\User\Registration
 */
class Registration extends XFCP_Registration
{
    protected function setup()
    {
        parent::setup();

        $this->fieldMap['website'] = 'Profile.website';
    }

    public function checkForSpam()
    {
        /** @var ExtendedUserEntity $user */
        $user = $this->user;
        $user = $user->svSpamCloneEntity();
        $user->preSave();
        $this->applyExtraValidation();

        if ($user->getErrors())
        {
            $this->validationErrors = $user->getErrors();
            $this->validationComplete = true;

            return;
        }

        Globals::$shimRejectUser = \XF::options()->svSkipSavingRejectedUsersOnSignup ?? false;
        try
        {
            parent::checkForSpam();
        }
        finally
        {
            Globals::$shimRejectUser = false;
        }
    }

    protected function writeInitialChangeLogs()
    {
        $user = $this->user;
        $profile = $user->Profile;
        $changes = [];

        $options = \XF::options();
        $this->db()->beginTransaction();
        if ($profile !== null)
        {
            if ($options->svSADAB_requestWebsiteOnSignup ?? false)
            {
                $changes['website'] = ['', $profile->website ?? ''];
            }
            if ($options->registrationSetup['requireLocation'] ?? false)
            {
                $changes['location'] = ['', $profile->location ?? ''];
            }
            if ($options->registrationSetup['requireDob'] ?? false)
            {
                $changes['dob_year'] = ['', $profile->dob_year];
                $changes['dob_month'] = ['', $profile->dob_month];
                $changes['dob_day'] = ['', $profile->dob_day];
            }

            if ($changes)
            {
                Helper::repository(ChangeLogRepo::class)->logChanges('user', $user->user_id, $changes, $user->user_id);
            }
        }

        parent::writeInitialChangeLogs();

        $this->db()->commit();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

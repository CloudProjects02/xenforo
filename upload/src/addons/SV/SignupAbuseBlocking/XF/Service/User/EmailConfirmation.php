<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use SV\SignupAbuseBlocking\XF\Entity\User as UserEntity;
use function is_string;

/**
 * @Extends \XF\Service\User\EmailConfirmation
 */
class EmailConfirmation extends XFCP_EmailConfirmation
{
    protected function advanceUserState()
    {
        /** @var UserEntity $user */
        $user = $this->user;
        $wasInEmailConfirm = $user->user_state === 'email_confirm';

        parent::advanceUserState();

        if ($wasInEmailConfirm && $user->user_state === 'moderated' &&
            $user->isRequireEmailConfirmationFromApprovalQueue(false))
        {
            // XenForo pushes from email_confirm => moderated when manual approval is true
            // but "Require email confirmation (always notifies)" pushes from moderated => email_confirm
            // avoid the loop
            $this->user->user_state = 'valid';
        }

        $ip = $this->app->request()->getIp();
        if (!is_string($ip) || $ip === '')
        {
            $ip = null;
        }

        // This can happen as it means another user has used this user's email confirmation link(s). Log as an explicit multi-account trigger
        MultipleAccountRepo::get()->detectedCrossAccountPerAccountEmailLink(\XF::visitor(), $this->user, 'register', $ip);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

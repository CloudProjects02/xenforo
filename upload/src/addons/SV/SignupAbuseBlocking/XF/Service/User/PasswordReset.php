<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;

/**
 * @Extends \XF\Service\User\PasswordReset
 */
class PasswordReset extends XFCP_PasswordReset
{
    /** @noinspection PhpMissingReturnTypeInspection */
    public function resetLostPassword($newPassword)
    {
        $user = parent::resetLostPassword($newPassword);

        if (!$this->isAdminReset)
        {
            $ip = $this->app->request()->getIp();
            if (!is_string($ip) || $ip === '')
            {
                $ip = null;
            }

            // This can happen as it means another user has used this user's email confirmation link(s). Log as an explicit multi-account trigger
            MultipleAccountRepo::get()->detectedCrossAccountPerAccountEmailLink(\XF::visitor(), $this->user, 'login', $ip);
        }

        return $user;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

use SV\SignupAbuseBlocking\Repository\MultipleAccount as MultipleAccountRepo;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Service\FloodCheck as FloodCheckService;

class Login extends XFCP_Login
{
    /**
     * @param string $password
     * @param null   $error
     * @return null|UserEntity
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function validate($password, &$error = null)
    {
        $user = parent::validate($password, $error);

        $svLoginFloodLimit = \XF::options()->svLoginFloodLimit ?? 0;
        if ($user instanceof UserEntity && $svLoginFloodLimit > 0)
        {
            if (!$user->hasPermission('general', 'bypassFloodCheck'))
            {
                $floodChecker = Helper::service(FloodCheckService::class);
                $timeRemaining = (int)$floodChecker->checkFlooding('sv_login', $user->user_id, $svLoginFloodLimit);
                if ($timeRemaining > 0)
                {
                    $error = \XF::phrase('sv_must_wait_x_seconds_before_attempting_to_login', ['count' => $timeRemaining]);

                    // trigger multi-account detection, even tho we block the login
                    if ($user->user_id)
                    {
                        MultipleAccountRepo::get()->postLoginMultiAccountCheck($user);
                    }

                    return null;
                }
            }
        }

        return $user;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

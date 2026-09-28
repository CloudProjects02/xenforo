<?php

namespace SV\SignupAbuseBlocking\XF\Job;


use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Repository\Banning as BanningRepo;

/**
 * Class UserAction
 *
 * @package SV\UserEssentials\XF\Job
 */
class UserAction extends XFCP_UserAction
{
    protected function applyExternalUserChange(UserEntity $user)
    {
        $isBanning = $this->data['actions']['ban'] ?? null;
        if ($isBanning && !$user->is_admin && !$user->is_moderator)
        {
            $reason = $this->getActionValue('ban_reason') ?? '';
            $expiryDate = $this->getActionValue('ban_end_date') ?? 0;

            Helper::repository(BanningRepo::class)->banUser($user, $expiryDate, $reason);

            $this->data['actions']['ban'] = null;
        }
        try
        {
            parent::applyExternalUserChange($user);
        }
        finally
        {
            $this->data['actions']['ban'] = $isBanning;
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

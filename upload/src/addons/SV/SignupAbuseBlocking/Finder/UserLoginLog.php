<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\UserLoginLog as UserLoginLogEntity;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserLoginLogEntity>|UserLoginLogEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method UserLoginLogEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,UserLoginLogEntity>
 * @extends Finder<UserLoginLogEntity>
 */
class UserLoginLog extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }

    /**
     * @param UserEntity $user
     * @return static
     */
    public function byUser(UserEntity $user)
    {
        $userId = (int)$user->user_id;
        if ($userId === 0)
        {
            return $this->whereImpossible();
        }

        return $this->where('user_id', $userId);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

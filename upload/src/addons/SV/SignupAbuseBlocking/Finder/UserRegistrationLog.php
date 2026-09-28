<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Entity\User as UserEntity;

/**
 * @method AbstractCollection<UserRegistrationLogEntity>|UserRegistrationLogEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method UserRegistrationLogEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,UserRegistrationLogEntity>
 * @extends Finder<UserRegistrationLogEntity>
 */
class UserRegistrationLog extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }

    /**
     * @param UserEntity|int|int[] $userId
     * @return self
     */
    public function forUser($userId): self
    {
        if ($userId instanceof UserEntity)
        {
            $userId = $userId->user_id;
        }

        $this->where('user_id', '=', $userId);
        $this->order('log_date','desc');

        return $this;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

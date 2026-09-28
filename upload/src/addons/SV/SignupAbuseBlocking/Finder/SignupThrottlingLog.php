<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\SignupThrottlingLog as SignupThrottlingLogEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SignupThrottlingLogEntity>|SignupThrottlingLogEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method SignupThrottlingLogEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,SignupThrottlingLogEntity>
 * @extends Finder<SignupThrottlingLogEntity>
 */
class SignupThrottlingLog extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }

    public function forAdminList(): self
    {
        return $this->order('expiry_date');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

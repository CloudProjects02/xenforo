<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\MultiAccountUser as MultiAccountUserEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<MultiAccountUserEntity>|MultiAccountUserEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method MultiAccountUserEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,MultiAccountUserEntity>
 * @extends Finder<MultiAccountUserEntity>
 */
class MultiAccountUser extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

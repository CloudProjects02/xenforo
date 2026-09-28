<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\Token as TokenEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TokenEntity>|TokenEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method TokenEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,TokenEntity>
 * @extends Finder<TokenEntity>
 */
class Token extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

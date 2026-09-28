<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\AllowEmailDomain as AllowEmailDomainEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AllowEmailDomainEntity>|AllowEmailDomainEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method AllowEmailDomainEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,AllowEmailDomainEntity>
 * @extends Finder<AllowEmailDomainEntity>
 */
class AllowEmailDomain extends AbstractAllowOrBanItem
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

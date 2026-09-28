<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\LogEvent as LogEventEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<LogEventEntity>|LogEventEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method LogEventEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,LogEventEntity>
 * @extends Finder<LogEventEntity>
 */
class LogEvent extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

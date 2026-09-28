<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\ReportData as ReportDataEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ReportDataEntity>|ReportDataEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method ReportDataEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,ReportDataEntity>
 * @extends Finder<ReportDataEntity>
 */
class ReportData extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

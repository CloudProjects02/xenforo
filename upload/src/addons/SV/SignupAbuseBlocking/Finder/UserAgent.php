<?php

namespace SV\SignupAbuseBlocking\Finder;

use SV\SignupAbuseBlocking\Entity\UserAgent as UserAgentEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserAgentEntity>|UserAgentEntity[] fetch(?int $limit = null, ?int $offset = null)
 * @method UserAgentEntity|null fetchOne(?int $offset = null)
 * @implements \IteratorAggregate<string|int,UserAgentEntity>
 * @extends Finder<UserAgentEntity>
 */
class UserAgent extends Finder
{
    public static function finder(): self
    {
        return Helper::finder(self::class);
    }

    /**
     * @param string $userAgent
     * @param string $hash
     * @return static
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function byUserAgent(string $userAgent, string $hash)//: static
    {
        $this->where('user_agent_hash', $hash)
             ->where('user_agent', $userAgent)
        ;

        return $this;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

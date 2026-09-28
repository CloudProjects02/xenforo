<?php

namespace SV\SignupAbuseBlocking\MultipleAccount;

use SV\SignupAbuseBlocking\Entity\Log as LogEntity;
use SV\SignupAbuseBlocking\Entity\Token as TokenEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;

class DetectionRecord
{
    /** @var ExtendedUserEntity */
    public $user;
    /** @var DetectionMethod[] */
    public $methods;
    /** @var LogEntity */
    public $log;
    /** @var TokenEntity */
    public $token;

    /**
     * DetectionRecord constructor.
     *
     * @param ExtendedUserEntity $user
     * @param DetectionMethod[]  $methods
     * @param TokenEntity|null   $token
     */
    public function __construct(ExtendedUserEntity $user, array $methods, ?TokenEntity $token = null)
    {
        $this->user = $user;
        $this->methods = $methods;
        $this->token = $token;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

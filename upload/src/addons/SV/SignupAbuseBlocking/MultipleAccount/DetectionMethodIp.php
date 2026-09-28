<?php

namespace SV\SignupAbuseBlocking\MultipleAccount;


use function array_merge;
use function array_unique;
use function is_array;
use function join;

class DetectionMethodIp extends DetectionMethod
{
    /** @var string[] */
    public $ipAddress;

    /**
     * DetectionMethodCookie constructor.
     *
     * @param string|string[] $ipAddress
     */
    public function __construct($ipAddress)
    {
        parent::__construct('ip');
        if (!is_array($ipAddress))
        {
            $ipAddress = [$ipAddress];
        }
        $this->ipAddress = $ipAddress;
    }

    /**
     * @param string|string[] $ipAddress
     */
    public function addIps($ipAddress)
    {
        if (!is_array($ipAddress))
        {
            $ipAddress = [$ipAddress];
        }

        $this->ipAddress = array_unique(array_merge($this->ipAddress, $ipAddress), \SORT_STRING);
    }

    public function forPhraseData(): array
    {
        return ['ip' => join(', ', $this->ipAddress)];
    }

    public function combine(DetectionMethod $method): void
    {
        if ($method instanceof DetectionMethodIp)
        {
            $this->addIps($method->ipAddress);
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

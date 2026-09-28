<?php

namespace SV\SignupAbuseBlocking\Spam;


use ArrayObject;
use XF\App;

abstract class AsnProvider extends ApiProvider
{
    /**
     * @var array
     */
    protected $asnConfig;

    public function __construct(App $app, ArrayObject $xfOptions, array $asnConfig)
    {
        parent::__construct($app, $xfOptions);
        $this->asnConfig = $asnConfig;
    }

    abstract public function resolveIpToAsn(string $ip): ?array;

    public function isInteractive(): bool
    {
        return true;
    }

    public function isNonInteractive(): bool
    {
        return false;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

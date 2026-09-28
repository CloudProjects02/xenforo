<?php

namespace SV\SignupAbuseBlocking\Spam;


use ArrayObject;
use XF\App;

abstract class GeoIpProvider extends ApiProvider
{
    /**
     * @var array
     */
    protected $geoIpConfig;

    public function __construct(App $app, ArrayObject $xfOptions, array $geoIpConfig)
    {
        parent::__construct($app, $xfOptions);
        $this->geoIpConfig = $geoIpConfig;
    }

    abstract public function resolveGeoIp(string $ip): ?string;

    public function isInteractive(): bool
    {
        return true;
    }

    public function isNonInteractive(): bool
    {
        return false;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

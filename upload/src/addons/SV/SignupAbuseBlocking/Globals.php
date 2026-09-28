<?php


namespace SV\SignupAbuseBlocking;

use SV\SignupAbuseBlocking\MultipleAccount\DetectionMethod;
use SV\SignupAbuseBlocking\MultipleAccount\DetectionRecord;
use SV\SignupAbuseBlocking\XF\Entity\User as UserEntity;

abstract class Globals
{
    private function __construct() { }

    /** @var bool  */
    public static $armLoginPlugin = false;

    /** @var array<int,DetectionRecord>|null */
    public static $dataCollection = null;

    /** @var bool */
    public static $shimRejectUser = false;

    /** @var bool  */
    public static $duringRegistration = false;

    /** @var bool  */
    public static $redirectedRegistration = false;

    /** @var string|null */
    public static $asnCountryLookupFallback = null;

    /** @var bool */
    public static $filterChangeLogItems = false;

    /**  @var UserEntity|null */
    public static $userBeforeLogin = null;

    /** @var DetectionMethod|null */
    public static $userBeforeLoginType = null;

    /** @var string|null */
    public static $loginAction = null;

    public static function withLoginAction(string $loginAction, \Closure $callable)
    {
        $oldLoginAction = self::$loginAction;
        self::$loginAction = $loginAction;
        try
        {
            return $callable();
        }
        finally
        {
            self::$loginAction = $oldLoginAction;
        }
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

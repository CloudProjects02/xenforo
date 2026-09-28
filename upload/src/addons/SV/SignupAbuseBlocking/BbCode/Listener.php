<?php


namespace SV\SignupAbuseBlocking\BbCode;

use SV\StandardLib\Helper;
use XF\BbCode\Renderer\AbstractRenderer;

class Listener
{
    public static function bbCodeRender(AbstractRenderer $renderer, string $type)
    {
        $obj = Helper::newExtendedClass(MultiAccountBlock::class, $renderer, $type);
        $obj->bindToRenderer();
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

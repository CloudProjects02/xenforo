<?php

namespace SV\SignupAbuseBlocking\MultipleAccount;

abstract class DetectionMethod
{
    /** @var string */
    public $method;

    public function __construct(string $method)
    {
        $this->method = $method;
    }

    abstract public function forPhraseData(): array;

    public function combine(DetectionMethod $method): void {}
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

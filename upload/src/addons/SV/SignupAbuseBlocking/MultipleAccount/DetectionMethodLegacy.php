<?php

namespace SV\SignupAbuseBlocking\MultipleAccount;

class DetectionMethodLegacy extends DetectionMethod
{
    /** @var string */
    public $receivedToken;

    public function __construct(string $receivedToken)
    {
        parent::__construct('legacy');
        $this->receivedToken = $receivedToken;
    }

    public function forPhraseData(): array
    {
        return ['legacy' => $this->receivedToken];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

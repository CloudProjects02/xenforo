<?php

namespace SV\SignupAbuseBlocking\MultipleAccount;

class DetectionMethodCookie extends DetectionMethod
{
    /** @var string */
    public $expectedToken;
    /** @var string */
    public $receivedToken;

    public function __construct(string $expectedToken, string $receivedToken)
    {
        parent::__construct('cookie');
        $this->expectedToken = $expectedToken;
        $this->receivedToken = $receivedToken;
    }

    public function forPhraseData(): array
    {
        return ['expected' => $this->expectedToken, 'received' => $this->receivedToken];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

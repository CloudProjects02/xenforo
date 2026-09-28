<?php

namespace SV\SignupAbuseBlocking\Spam;

interface IScoringChecker
{
    public function logSimpleDetail(string $phrase, array $phraseData = []);

    /**
     * @param string $phrase
     * @param string|float|int $score
     * @param array $phraseData
     */
    public function logScore(string $phrase, $score, array $phraseData = []);

    public function logScoreAccept(string $phrase, array $phraseData = []);

    public function logScoreReject(string $phrase, array $phraseData = []);

    public function logScoreModerate(string $phrase, array $phraseData = []);

    public function patchLogDecision(bool $start): bool;
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

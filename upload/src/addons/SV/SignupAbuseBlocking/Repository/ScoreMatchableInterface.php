<?php

namespace SV\SignupAbuseBlocking\Repository;

interface ScoreMatchableInterface
{
    /**
     * @param string|string[]  $input
     * @param float|int|string $score
     * @param string           $matchRule
     * @param string           $matchInput
     * @return bool
     */
    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool;
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

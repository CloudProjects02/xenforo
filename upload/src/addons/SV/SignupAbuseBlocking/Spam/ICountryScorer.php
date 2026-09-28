<?php

namespace SV\SignupAbuseBlocking\Spam;

interface ICountryScorer
{
    public function getCountry(): string;

    public function getBrowserLanguages(): string;

    public function setBrowserLanguages(string $languages);

    public function getBrowserTimezone(): string;

    public function setBrowserTimezone(string $timezone);
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

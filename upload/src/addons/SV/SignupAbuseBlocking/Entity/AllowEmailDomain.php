<?php

namespace SV\SignupAbuseBlocking\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class AllowEmailDomain
 *
 * @package SV\SignupAbuseBlocking\Entity
 */
class AllowEmailDomain extends AbstractAllowOrBanItem
{
    /**
     * @param string $emailDomain
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function verifyEmailDomain(string &$emailDomain): bool
    {
        // todo: set logic here

        return true;
    }

    public static function getStructure(Structure $structure): Structure
    {
        static::setupDefaultStructure(
            $structure,
            'SV\SignupAbuseBlocking:AllowEmailDomain',
            'xf_sv_signup_abuse_blocking_allow_email_domain',
            'email_domain'
        );

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

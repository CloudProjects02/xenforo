<?php

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;

/**
 * @Extends \XF\Pub\Controller\Misc
 */
class Misc extends XFCP_Misc
{
    public function actionAsnInfo()
    {
        if (!\XF::visitor()->canViewIps())
        {
            return $this->notFound();
        }

        $asn = $this->filter('asn', 'str');
        $url = UserRegistrationLogRepo::get()->getAsnLink($asn);

        return $this->redirectPermanently($url);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

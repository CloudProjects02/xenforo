<?php

namespace SV\SignupAbuseBlocking\XF\Pub\Controller;

class Logout extends XFCP_Logout
{
    public function assertNotBanned()
    {
        if (\XF::options()->svAllowBannedLogout ?? false)
        {
            return;
        }

        parent::assertNotBanned();
    }

    public function assertNotRejected($action)
    {
        if ((\XF::options()->svAllowRejectedLogout ?? false))
        {
            return;
        }

        parent::assertNotRejected($action);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

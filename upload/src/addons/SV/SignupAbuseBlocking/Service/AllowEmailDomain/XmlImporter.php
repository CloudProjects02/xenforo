<?php

namespace SV\SignupAbuseBlocking\Service\AllowEmailDomain;

use SV\SignupAbuseBlocking\Service\AbstractAllowOrBanItemXmlImporter as ItemXmlImporterSvc;

class XmlImporter extends ItemXmlImporterSvc
{
    protected function getIdentifier(): string
    {
        return 'SV\SignupAbuseBlocking:AllowEmailDomain';
    }

    public function getRootName(): string
    {
        return 'allowed_email_domains';
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

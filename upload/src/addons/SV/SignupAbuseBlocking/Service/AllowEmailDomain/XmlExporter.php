<?php

namespace SV\SignupAbuseBlocking\Service\AllowEmailDomain;

use SV\SignupAbuseBlocking\Service\AbstractAllowOrBanItemXmlExporter as ItemXmlExporterSvc;

class XmlExporter extends ItemXmlExporterSvc
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
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

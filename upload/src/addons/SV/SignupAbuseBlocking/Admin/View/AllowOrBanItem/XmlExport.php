<?php

namespace SV\SignupAbuseBlocking\Admin\View\AllowOrBanItem;

use XF\Http\Response as HttpResponse;
use XF\Mvc\View;

class XmlExport extends View
{
    protected function getXmlFileName(): string
    {
        return $this->getParams()['xmlFileName'];
    }

    protected function getXml(): \DOMDocument
    {
        return $this->getParams()['xml'];
    }

    public function renderXml(): string
    {
        $response = $this->getResponse();

        $response->contentType('application/xml');
        $response->setDownloadFileName($this->getXmlFileName());

        return $this->getXml()->saveXML();
    }

    protected function getResponse(): HttpResponse
    {
        return $this->response;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

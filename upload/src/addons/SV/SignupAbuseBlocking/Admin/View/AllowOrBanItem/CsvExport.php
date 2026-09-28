<?php

namespace SV\SignupAbuseBlocking\Admin\View\AllowOrBanItem;

use XF\Http\Response as HttpResponse;
use XF\Mvc\View;

class CsvExport extends View
{
    protected function getCsvFileName(): string
    {
        return $this->params['csvFileName'] ?? '';
    }

    protected function getCsv(): string
    {
        return $this->params['csv'] ?? '';
    }

    public function renderRaw(): string
    {
        $response = $this->getResponse();

        $response->contentType('text/csv');
        $response->setDownloadFileName($this->getCsvFileName());

        return $this->getCsv();
    }

    protected function getResponse(): HttpResponse
    {
        return $this->response;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

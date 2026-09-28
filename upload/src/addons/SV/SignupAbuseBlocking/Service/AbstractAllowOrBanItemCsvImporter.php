<?php

namespace SV\SignupAbuseBlocking\Service;

abstract class AbstractAllowOrBanItemCsvImporter extends AbstractCsvImport
{
    protected function getColumns(): array
    {
        return [
            $this->getPrimaryKey(),
            'reason',
        ];
    }

    protected function importEntry(array $columns): void
    {
        $this->getItemRepo()->addItem(
            (string)$columns[$this->getPrimaryKey()],
            $columns['reason'] ?? ''
        );
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

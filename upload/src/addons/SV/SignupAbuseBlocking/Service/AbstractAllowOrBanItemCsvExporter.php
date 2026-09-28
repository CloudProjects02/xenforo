<?php

namespace SV\SignupAbuseBlocking\Service;

use SV\SignupAbuseBlocking\Entity\AbstractAllowOrBanItem;
use XF\Mvc\Entity\Entity;

abstract class AbstractAllowOrBanItemCsvExporter extends AbstractCsvExport
{
    protected function getColumns(): ?array
    {
        return [
            $this->getPrimaryKey(),
            'reason'
        ];
    }

    /**
     * @param Entity|AbstractAllowOrBanItem $entity
     * @return array
     */
    protected function exportEntry(Entity $entity): array
    {
        return [
            $entity->getEntityId(),
            $entity->reason
        ];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

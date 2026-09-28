<?php

namespace SV\SignupAbuseBlocking\Service;

use SV\SignupAbuseBlocking\Entity\AbstractAllowOrBanItem as ItemEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure as EntityStructure;
use XF\Service\AbstractXmlExport;

abstract class AbstractAllowOrBanItemXmlExporter extends AbstractXmlExport
{
    abstract protected function getIdentifier(): string;

    /**
     * @param Entity|ItemEntity $entity
     * @param \DOMElement $node
     */
    protected function exportEntry(Entity $entity, \DOMElement $node)
    {
        $reasonNode = $node->ownerDocument->createElement('reason');
        $this->exportCdata($reasonNode, $entity->reason);
        $node->appendChild($reasonNode);
    }

    /**
     * @return string[]
     */
    protected function getAttributes(): array
    {
        return [$this->getPrimaryKey()];
    }

    protected function getStructure(): EntityStructure
    {
        return $this->em()->getEntityStructure($this->getIdentifier());
    }

    /**
     * @return string|int
     */
    protected function getPrimaryKey()
    {
        return $this->getStructure()->primaryKey;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

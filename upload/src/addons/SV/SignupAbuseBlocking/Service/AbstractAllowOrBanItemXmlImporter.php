<?php

namespace SV\SignupAbuseBlocking\Service;

use SV\SignupAbuseBlocking\Repository\AbstractAllowOrBanItem as ItemRepo;
use XF\App as BaseApp;
use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\Structure as EntityStructure;
use XF\Service\AbstractXmlImport;
use XF\Util\Xml as XmlUtil;

abstract class AbstractAllowOrBanItemXmlImporter extends AbstractXmlImport
{
    abstract protected function getIdentifier(): string;

    public function import(\SimpleXMLElement $xml)
    {
        $primaryKey = $this->getPrimaryKey();

        $itemRepo = $this->getItemRepo();
        foreach ($itemRepo->findItems()->fetchColumns([$primaryKey]) as $row)
        {
            $itemCache[\strtolower($row[$primaryKey])] = true;
        }

        $entries = $xml->entry;
        foreach ($entries AS $entry)
        {
            if (isset($itemCache[strtolower((string) $entry[$primaryKey])]))
            {
                // already exists
                continue;
            }

            $itemRepo->addItem(
                (string) $entry[$primaryKey],
                XmlUtil::processSimpleXmlCdata($entry->reason)
            );
        }
    }

    protected function app(): BaseApp
    {
        return $this->app;
    }

    /**
     * @return Repository|ItemRepo
     */
    protected function getItemRepo(): ItemRepo
    {
        return $this->app()->repository($this->getIdentifier());
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
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

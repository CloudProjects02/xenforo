<?php

namespace SV\SignupAbuseBlocking\Service;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Structure as EntityStructure;
use XF\Service\AbstractService;
use function fclose;
use function fopen;
use function fputcsv;
use function fseek;
use function stream_get_contents;
use function trim;

abstract class AbstractCsvExport extends AbstractService
{
    /**
     * @param Finder $finder
     * @return string
     */
    public function export(Finder $finder): string
    {
        $entities = $finder->fetch();

        $csvResource = fopen('php://memory', 'w+');
        try
        {
            $columns = $this->getColumns();
            if ($columns !== null)
            {
                fputcsv($csvResource, $columns);
            }
            foreach ($entities as $entity)
            {
                $line = $this->exportEntry($entity);

                if ($line !== null)
                {
                    fputcsv($csvResource, $line);
                }
            }
            fseek($csvResource, 0);
            $csvContents = trim(stream_get_contents($csvResource));
        }
        finally
        {
            fclose($csvResource);
        }

        return $csvContents;
    }

    abstract protected function getColumns(): ?array;
    abstract protected function exportEntry(Entity $entity): ?array;

    abstract protected function getIdentifier(): string;

    protected function getStructure():EntityStructure
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
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

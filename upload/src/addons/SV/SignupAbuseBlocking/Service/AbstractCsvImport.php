<?php

namespace SV\SignupAbuseBlocking\Service;

use SV\SignupAbuseBlocking\Repository\AbstractAllowOrBanItem as ItemRepo;
use XF\App as BaseApp;
use XF\Mvc\Entity\Structure as EntityStructure;
use XF\Service\AbstractService;
use function array_combine;
use function fclose;
use function fgetcsv;
use function fopen;
use function fwrite;
use function is_array;
use function rewind;
use function strtolower;

/**
 * Class AbstractCsvImport
 *
 * @package SV\SignupAbuseBlocking\Service
 */
abstract class AbstractCsvImport extends AbstractService
{
    abstract protected function getIdentifier(): string;
    abstract protected function getColumns(): array;
    abstract protected function importEntry(array $columns): void;

    public function import(string $csv): void
    {
        $columns = $this->getColumns();
        $primaryKey = $this->getPrimaryKey();
        $itemRepo = $this->getItemRepo();
        foreach ($itemRepo->findItems()->fetchColumns([$primaryKey]) as $row)
        {
            $itemCache[strtolower($row[$primaryKey])] = true;
        }

        $stream = fopen('php://memory', 'w+');
        try
        {
            fwrite($stream, $csv);
            rewind($stream);

            // remove header row
            fgetcsv($stream);

            while(true)
            {
                $row = fgetcsv($stream);
                if (!is_array($row))
                {
                    break;
                }
                if (count($row) === 0)
                {
                    continue;
                }

                $values = array_combine($columns, $row);
                $primaryValue = $values[$primaryKey] ?? null;

                if (isset($itemCache[strtolower((string) $primaryValue)]))
                {
                    // already exists
                    continue;
                }

                $this->importEntry($values);
            }
        }
        finally
        {
            fclose($stream);
        }
    }

    protected function app(): BaseApp
    {
        return $this->app;
    }

    protected function getItemRepo(): ItemRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
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
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

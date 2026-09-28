<?php

namespace SV\SignupAbuseBlocking\ControllerPlugin;

use SV\SignupAbuseBlocking\Service\AbstractCsvExport as AbstractCsvExportSvc;
use SV\SignupAbuseBlocking\Service\AbstractCsvImport as AbstractCsvImportSvc;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Reply\AbstractReply;
use function file_get_contents;

/**
 * Class Csv
 *
 * @package SV\SignupAbuseBlocking\ControllerPlugin
 */
class Csv extends AbstractPlugin
{
    public function actionExport(Finder $finder, string $serviceClass, ?string $viewClass = null): AbstractReply
    {
        if ($token = $this->filter('t', 'str'))
        {
            $this->assertValidCsrfToken($token);
        }
        else
        {
            $this->assertPostOnly();
        }

        $exportService = $this->service($serviceClass);
        if (!($exportService instanceof AbstractCsvExportSvc))
        {
            throw $this->exception($this->message(\XF::phrase('svSignupAbuseBlocking_class_x_must_extend_abstract_csv_export_class', [
                'exportService' => \get_class($exportService)
            ])));
        }
        $csv = $exportService->export($finder);

        $this->setResponseType('raw');

        $viewParams = [
            'csv' => $csv,
        ];
        return $this->view($viewClass ?: $serviceClass, '', $viewParams);
    }

    public function actionImport(string $urlPrefix,string $serviceClass, ?string $viewClass = null): AbstractReply
    {
        $type = $this->filter('type', 'str', 'xml');

        if ($this->isPost())
        {
            $upload = $this->request->getFile('upload', false);
            if (!$upload)
            {
                throw $this->exception($this->error(\XF::phrase('svSignupAbuseBlocking_please_provide_valid_csv_file')));
            }

            try
            {
                $csv = file_get_contents($upload->getTempFile());
            }
            catch (\Exception $e)
            {
                throw $this->exception($this->error(\XF::phrase('svSignupAbuseBlocking_please_provide_valid_csv_file')));
            }

            $importService = $this->service($serviceClass);
            if (!($importService instanceof AbstractCsvImportSvc))
            {
                throw $this->exception($this->message(\XF::phrase('svSignupAbuseBlocking_class_x_must_extend_abstract_csv_export_class', [
                    'importService' => \get_class($importService)
                ])));
            }
            $importService->import($csv);

            return $this->redirect($this->buildLink($urlPrefix));
        }

        $viewParams = [
            'type' => $type,
            'urlPrefix' => $urlPrefix
        ];
        return $this->view($serviceClass ?: $viewClass, 'svSignupAbuseBlocking_csv_import', $viewParams);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

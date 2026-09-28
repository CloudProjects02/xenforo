<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Entity;

use SV\SignupAbuseBlocking\Entity\ReportData;
use XF\Mvc\Entity\Structure;

/**
 * GETTERS
 *
 * @property-read array       $MultipleAccountLogs
 * RELATIONS
 * @property-read ?ReportData $ReportData
 */
class Report extends XFCP_Report
{
    /**
     * @return array|null
     */
    protected function getMultipleAccountLogs()
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewMultiAccountReport())
        {
            return null;
        }

        if ($this->exists() && $this->content_type === 'multiple_account')
        {
            $reportData = $this->ReportData;
            if (!empty($reportData))
            {
                return $reportData->getGroupedLogs();
            }
        }

        return null;
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['ReportData'] = [
            'entity'     => 'SV\SignupAbuseBlocking:ReportData',
            'type'       => self::TO_ONE,
            'conditions' => [
                ['report_id', '=', '$report_id'],
                ['active', '=', 1]
            ],
        ];

        $structure->getters['MultipleAccountLogs'] = ['getter' => 'getMultipleAccountLogs', 'cache' => true];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

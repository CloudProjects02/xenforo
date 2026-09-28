<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SignupAbuseBlocking\XF\Repository;

use SV\SignupAbuseBlocking\Finder\ReportData as ReportDataFinder;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use XF\Entity\Report as ReportEntity;

/**
 * @Extends \XF\Repository\Report
 */
class Report extends XFCP_Report
{
    /**
     * @param array $state
     * @param int|null  $timeFrame
     * @return \XF\Finder\Report
     */
    public function findReports($state = ['open', 'assigned'], $timeFrame = null)
    {
        $finder = parent::findReports($state, $timeFrame);

        $finder->with('ReportData');

        return $finder;
    }

    /**
     * @noinspection PhpDeprecationInspection
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection RedundantSuppression
     */
    public function filterViewableReports($reports)
    {
        $reports = parent::filterViewableReports($reports);

        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if ($reports && $reports->count() > 0 && $visitor->canViewMultiAccountReport())
        {
            $multiAccountReports = [];
            /** @var ReportEntity $report */
            foreach($reports as $reportId => $report)
            {
                if ($report->content_type === 'multiple_account')
                {
                    $multiAccountReports[$reportId] = $report;
                }
            }

            if ($multiAccountReports)
            {
                $reportData = ReportDataFinder::finder()
                                              ->where('report_id', '=', \array_keys($multiAccountReports))
                                              ->where('active', '=', 1)
                                              ->keyedBy('report_id')
                                              ->fetch();

                foreach ($multiAccountReports as $reportId => $report)
                {
                    $report->hydrateRelation('ReportData', isset($reportData[$reportId]) ? $reportData[$reportId] : null);
                }
            }
        }

        return $reports;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

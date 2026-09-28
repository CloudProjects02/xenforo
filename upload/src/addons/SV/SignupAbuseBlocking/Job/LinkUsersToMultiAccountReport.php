<?php

namespace SV\SignupAbuseBlocking\Job;

use SV\SignupAbuseBlocking\Entity\ReportData as ReportDataEntity;
use SV\StandardLib\Helper;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class LinkUsersToMultiAccountReport extends AbstractRebuildJob
{
    protected $defaultData = [
        'prune' => true,
    ];

    protected function setupData(array $data): array
    {
        $data = parent::setupData($data);

        if ($data['prune'])
        {
            $data['prune'] = false;
            \XF::db()->emptyTable('xf_sv_multiple_account_report_data_user');
        }

        return $data;
    }

    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT report_data_id
				FROM xf_sv_multiple_account_report_data
				WHERE report_data_id > ?
				ORDER BY report_data_id
			', $batch
        ), [$start]);
    }

    protected function rebuildById($id): void
    {
        $reportData = Helper::find(ReportDataEntity::class, $id);
        if ($reportData !== null)
        {
            $reportData->rebuildUsers(false);
        }
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('sv_multiple_accounts_detection_events');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

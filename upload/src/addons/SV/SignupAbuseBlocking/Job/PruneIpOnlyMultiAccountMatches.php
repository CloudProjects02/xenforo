<?php

namespace SV\SignupAbuseBlocking\Job;

use SV\SignupAbuseBlocking\Entity\LogEvent;
use SV\StandardLib\Helper;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class PruneIpOnlyMultiAccountMatches extends AbstractRebuildJob
{
    protected $defaultData = [
        'doRebuild' => false,
    ];

    protected function getNextIds($start, $batch): array
    {
        $db = \XF::db();

        $results = $db->fetchAllColumn($db->limit('
            SELECT DISTINCT event_id
            FROM xf_sv_multiple_account_log
            WHERE event_id > ? AND detection_methods LIKE ?
            ORDER BY event_id            
        ', $batch), [$start, '{"ip":{"ip":%']);

        if (count($results) === 0 && $this->data['doRebuild'])
        {
            \XF::app()->jobManager()->enqueueUnique('svRebuildMultiAccountSummary', RebuildMultiAccountSummary::class, [], true);
        }

        return $results;
    }

    protected function rebuildById($id): void
    {
        $db = \XF::db();

        $logEvent = Helper::find(LogEvent::class, $id);
        if ($logEvent !== null)
        {
            // verify this is an ip only multi-account detection event
            foreach ($logEvent->Logs as $log)
            {
                $detectionMethods = $log->detection_methods ?? [];
                unset($detectionMethods['ip']);
                if (count($detectionMethods) !== 0)
                {
                    return;
                }
            }

            $reportDataId = $logEvent->report_data_id;
        }
        else
        {
            $reportDataId = null;
        }

        $db->beginTransaction();

        $db->query('
            DELETE 
            FROM xf_sv_multiple_account_log
            WHERE event_id = ?
        ', $id);

        $db->query('
            DELETE 
            FROM xf_sv_multiple_account_event
            WHERE event_id = ?
        ', $id);

        if ($reportDataId !== null)
        {
            $count = (int)$db->fetchOne('
                SELECT COUNT(*) 
                FROM xf_sv_multiple_account_event 
                WHERE report_data_id = ?
            ', $reportDataId);
            if ($count === 0)
            {
                $db->query('
                    DELETE 
                    FROM xf_sv_multiple_account_report_data
                    WHERE report_data_id = ?
                ', $reportDataId);
            }
            else
            {
                $db->query('
                    UPDATE xf_sv_multiple_account_report_data
                    set log_count = ?
                    WHERE report_data_id = ?
                ', [$count, $reportDataId]);
            }

            $db->query('
                DELETE 
                FROM xf_sv_multiple_account_report_data_user
                WHERE report_data_id = ?
            ', $reportDataId);
        }

        $db->commit();

        if (!$this->data['doRebuild'])
        {
            $this->data['doRebuild'] = true;
            $this->saveIncrementalData();
        }
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('sv_multiple_accounts_detection_events');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

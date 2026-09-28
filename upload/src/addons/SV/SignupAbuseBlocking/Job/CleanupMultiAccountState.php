<?php

namespace SV\SignupAbuseBlocking\Job;

use XF\Job\AbstractJob;
use XF\Job\JobResult;

class CleanupMultiAccountState extends AbstractJob
{
    protected $defaultData = [
        'state' => 'recountLog',
    ];

    /**
     * @param float|null $maxRunTime
     * @return JobResult
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function run($maxRunTime): JobResult
    {
        $db = $this->app->db();

        $s = \microtime(true);
        while ($this->data['state'])
        {
            switch ($this->data['state'])
            {
                case 'recountLog':
                    /** @noinspection SqlWithoutWhere */
                    $db->query('
                      UPDATE xf_sv_multiple_account_event
                      SET log_count = COALESCE((SELECT COUNT(*) 
                                                FROM xf_sv_multiple_account_log
                                                WHERE xf_sv_multiple_account_log.event_id = xf_sv_multiple_account_event.event_id), 0)
                    ');
                    $this->data['state'] = 'pruneEventTable1';
                    break;
                case 'pruneEventTable1':
                    $db->query('DELETE FROM xf_sv_multiple_account_event WHERE log_count = 0');
                    $this->data['state'] = 'pruneEventTable2';
                    break;
                case 'pruneEventTable2':
                    $db->query('
                        DELETE FROM xf_sv_multiple_account_log 
                        WHERE NOT EXISTS(
                            SELECT event_id 
                            FROM xf_sv_multiple_account_event 
                            WHERE xf_sv_multiple_account_log.event_id = xf_sv_multiple_account_event.event_id
                    )');
                    $this->data['state'] = 'recountReportData';
                    break;
                case 'recountReportData':
                    /** @noinspection SqlWithoutWhere */
                    $db->query('
                        UPDATE xf_sv_multiple_account_report_data
                        SET log_count = COALESCE((SELECT COUNT(*) 
                                                  FROM xf_sv_multiple_account_log 
                                                  JOIN xf_sv_multiple_account_event ON xf_sv_multiple_account_event.event_id = xf_sv_multiple_account_log.event_id
                                                  WHERE xf_sv_multiple_account_event.report_data_id = xf_sv_multiple_account_report_data.report_data_id), 0)
                    ');
                    $this->data['state'] = 'pruneReportDataTable1';
                    break;
                case 'pruneReportDataTable1':
                    $db->query('DELETE FROM xf_sv_multiple_account_report_data WHERE log_count = 0');
                    $this->data['state'] = 'pruneReportDataTable2';
                    break;
                case 'pruneReportDataTable2':
                    $db->query('
                        DELETE FROM xf_sv_multiple_account_report_data 
                        WHERE NOT EXISTS(
                            SELECT report_data_id 
                            FROM xf_sv_multiple_account_event 
                            WHERE xf_sv_multiple_account_report_data.report_data_id = xf_sv_multiple_account_event.report_data_id
                    )');
                    // done
                    $this->data['state'] = '';
                    break;
            }

            $this->saveIncrementalData();
            if ($maxRunTime && microtime(true) - $s > $maxRunTime)
            {
                return $this->resume();
            }
        }

        return $this->complete();
    }

    public function getStatusMessage(): string
    {
        return '';
    }

    public function canCancel(): bool
    {
        return false;
    }

    public function canTriggerByChoice(): bool
    {
        return false;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

<?php

namespace SV\SignupAbuseBlocking\XF\Service\User;

class Merge extends XFCP_Merge
{
    /** @noinspection SqlRedundantCodeInCoalesce */
    protected function postMergeCleanUp()
    {
        parent::postMergeCleanUp();

        $db = $this->db();
        $targetUserId = $this->target->user_id;
        $sourceUserId = $this->source->user_id;

        // ensure detected source user => target user multi-account records are not lost

        $db->query('
          UPDATE xf_sv_multiple_account_log
          join xf_sv_multiple_account_event on xf_sv_multiple_account_event.event_id = xf_sv_multiple_account_log.event_id
          SET xf_sv_multiple_account_log.user_id = 0, xf_sv_multiple_account_log.username = coalesce(xf_sv_multiple_account_log.username, ?) 
          WHERE xf_sv_multiple_account_log.user_id = ? and xf_sv_multiple_account_event.triggering_user_id = ?
          ', [$this->source->username, $sourceUserId, $targetUserId]);

        $db->query('
          UPDATE xf_sv_multiple_account_log
          join xf_sv_multiple_account_event on xf_sv_multiple_account_event.event_id = xf_sv_multiple_account_log.event_id
          SET xf_sv_multiple_account_event.triggering_user_id = 0, xf_sv_multiple_account_event.username = coalesce(xf_sv_multiple_account_event.username, ?) 
          WHERE xf_sv_multiple_account_event.triggering_user_id = ? and xf_sv_multiple_account_log.user_id = ?
          ', [$this->source->username, $sourceUserId, $targetUserId]);

        // rewrite other multi-account records

        $db->query('
          UPDATE xf_sv_multiple_account_log
          SET user_id = ?
          WHERE user_id = ?
          ', [$targetUserId, $sourceUserId]);

        $db->query('
          UPDATE xf_sv_multiple_account_event
          SET triggering_user_id = ?
          WHERE triggering_user_id = ?
          ', [$targetUserId, $sourceUserId]);

        $db->query('
          UPDATE xf_sv_multiple_account_token
          SET active = NULL, user_id = ?
          WHERE user_id = ?
          ', [$targetUserId, $sourceUserId]);

        $db->query('
          UPDATE xf_sv_user_registration_log 
          SET user_id = ? 
          WHERE user_id = ?
        ', [$targetUserId, $sourceUserId]);

        $db->query('
          UPDATE xf_sv_login_log 
          SET user_id = ? 
          WHERE user_id = ?
        ', [$targetUserId, $sourceUserId]);

        $db->query('
              UPDATE xf_sv_multiple_account_event
              SET log_count = COALESCE((SELECT count(*) 
                                        FROM xf_sv_multiple_account_log
                                        WHERE xf_sv_multiple_account_log.event_id = xf_sv_multiple_account_event.event_id), 0)
              WHERE event_id IN (
                  SELECT DISTINCT event_id 
                  FROM xf_sv_multiple_account_log
                  WHERE user_id = ?)
        ', [$targetUserId]);

        $db->query('
          UPDATE xf_sv_multiple_account_report_data
          SET log_count = COALESCE((SELECT count(*) 
                                    FROM xf_sv_multiple_account_log 
                                    JOIN xf_sv_multiple_account_event ON xf_sv_multiple_account_event.event_id = xf_sv_multiple_account_log.event_id
                                    WHERE xf_sv_multiple_account_event.report_data_id = xf_sv_multiple_account_report_data.report_data_id), 0) 
          WHERE report_data_id IN (
              SELECT DISTINCT svEvent.report_data_id 
              FROM xf_sv_multiple_account_log AS svLog
              JOIN xf_sv_multiple_account_event AS svEvent ON svEvent.event_id = svLog.event_id
              WHERE svLog.user_id = ? OR svEvent.triggering_user_id = ?)
          ', [$targetUserId, $targetUserId]);

        $db->query('
          UPDATE IGNORE xf_sv_multiple_account_report_data_user
          SET user_id = ? 
          WHERE user_id = ?
        ', [$targetUserId, $sourceUserId]);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

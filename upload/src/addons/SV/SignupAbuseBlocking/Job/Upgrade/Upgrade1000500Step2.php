<?php

namespace SV\SignupAbuseBlocking\Job\Upgrade;

use SV\StandardLib\Helper;
use XF\Entity\SpamTriggerLog as SpamTriggerLogEntity;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class Upgrade1000500Step2 extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            "
				SELECT trigger_log_id
				FROM xf_spam_trigger_log
				WHERE trigger_log_id > ? and content_type = 'user' and details like '%sv_reg_log.port_scan_%'
				ORDER BY trigger_log_id
			", $batch
        ), [$start]);
    }

    protected function rebuildById($id)
    {
        $db = $this->app->db();

        $db->beginTransaction();

        $log = Helper::find(SpamTriggerLogEntity::class, $id);
        if ($log === null)
        {
            $db->commit();

            return;
        }

        $details = $log->details_;
        $portToKeep = 0;

        foreach ($details AS &$detail)
        {
            if (empty($detail['phrase']))
            {
                continue;
            }

            if (stripos($detail['phrase'], 'sv_reg_log.port_scan_') !== 0)
            {
                continue;
            }

            if (isset($detail['data']['email']))
            {
                $detail['data']['port'] = $detail['data']['email'];
                unset($detail['data']['email']);
            }

            if (\is_array($detail['data']['port']))
            {
                if (isset($detail['data']['port'][$portToKeep]))
                {
                    $detail['data']['port'] = (int)$detail['data']['port'][$portToKeep];
                }
                else
                {
                    $detail['data']['port'] = (int)\reset($detail['data']['port']);
                }
            }

            $portToKeep += 1;
        }
        $log->details = $details;

        $log->saveIfChanged($saved, true, false);

        $db->commit();
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('spam_trigger_log');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

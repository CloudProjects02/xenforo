<?php

namespace SV\SignupAbuseBlocking\Job\Upgrade;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\StandardLib\Helper;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class Upgrade1000500Step1 extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            "
				SELECT user_registration_log_id
				FROM xf_sv_user_registration_log
				WHERE user_registration_log_id > ? and details like '%sv_reg_log.port_scan_%'
				ORDER BY user_registration_log_id
			", $batch
        ), [$start]);
    }

    protected function rebuildById($id)
    {
        $db = $this->app->db();

        $db->beginTransaction();

        $log = Helper::find(UserRegistrationLogEntity::class, $id);
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
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

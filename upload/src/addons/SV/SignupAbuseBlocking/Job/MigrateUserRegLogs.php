<?php

namespace SV\SignupAbuseBlocking\Job;

use SV\SignupAbuseBlocking\Entity\UserRegistrationLog as UserRegistrationLogEntity;
use SV\StandardLib\Helper;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class MigrateUserRegLogs extends AbstractRebuildJob
{
    protected function setupData(array $data): array
    {
        $this->defaultData['phrases'] = [];

        return parent::setupData($data);
    }

    protected function getNextIds($start, $batch): array
    {
        if (empty($this->data['phrases']) || !\is_array($this->data['phrases']))
        {
            return [];
        }

        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            "
				SELECT user_registration_log_id
				FROM xf_sv_user_registration_log
				WHERE user_registration_log_id > ? and (details like '%tpu%' or details like '%aed%')
				ORDER BY user_registration_log_id
			", $batch
        ), [$start]);
    }

    protected function rebuildById($id)
    {
        $phrases = $this->data['phrases'];

        $db = $this->app->db();

        $db->beginTransaction();

        $log = Helper::find(UserRegistrationLogEntity::class, $id);
        if ($log === null)
        {
            $db->commit();

            return;
        }

        $details = $log->details_;

        foreach ($details AS &$detail)
        {
            if (empty($detail['phrase']))
            {
                continue;
            }

            if (isset($phrases[$detail['phrase']]))
            {
                $detail['phrase'] = $phrases[$detail['phrase']];
            }
            else
            {
                if (stripos($detail['phrase'], 'tpu') !== false || stripos($detail['phrase'], 'aed') !== false)
                {
                    echo "Unknown phrase:{$detail['phrase']}\n";
                }
            }

            if (empty($detail['data']['reason']))
            {
                continue;
            }
            $reason = $detail['data']['reason'];

            if (isset($phrases[$reason]))
            {
                // this is logged as the phrase, remove the dupe
                unset($detail['data']['reason']);
            }
            else
            {
                if (stripos($reason, 'tpu') !== false || stripos($reason, 'aed') !== false)
                {
                    echo "Unknown phrase:{$reason}\n";
                }
            }
        }
        $log->details = $details;

        $log->saveIfChanged($saved, true, false);

        $db->commit();
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('sv_registration_log');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

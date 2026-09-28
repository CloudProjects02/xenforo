<?php

namespace SV\SignupAbuseBlocking\Job;

use SV\SignupAbuseBlocking\Entity\MultiAccountUser;
use SV\StandardLib\Helper;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class RebuildMultiAccountSummary extends AbstractRebuildJob
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
            \XF::db()->emptyTable('xf_sv_multiple_account_user');
        }

        return $data;
    }

    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT DISTINCT user_id
				FROM xf_sv_multiple_account_report_data_user
				WHERE user_id > ?
				ORDER BY user_id
			', $batch
        ), [$start]);
    }

    protected function rebuildById($id): void
    {
        $multiAccountUser = Helper::find(MultiAccountUser::class, $id);
        if ($multiAccountUser === null)
        {
            $multiAccountUser = MultiAccountUser::create();
            $multiAccountUser->user_id = $id;
        }
        if ($multiAccountUser->rebuild())
        {
            $multiAccountUser->saveIfChanged();
        }
        else if ($multiAccountUser->exists())
        {
            $multiAccountUser->delete();
        }
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('sv_multiple_accounts');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

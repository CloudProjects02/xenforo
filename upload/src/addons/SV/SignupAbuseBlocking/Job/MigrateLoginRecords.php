<?php

namespace SV\SignupAbuseBlocking\Job;

use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class MigrateLoginRecords extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			', $batch
        ), [$start]);
    }

    protected function rebuildById($id): void
    {
        \XF::db()->query("
            insert into xf_sv_login_log (user_id, ip_address, log_date, action)
            select user_id, ip_address, log_date, 'register'
            from xf_sv_user_registration_log
            where user_id = ?
        ", [$id]);

        \XF::db()->query("
            insert into xf_sv_login_log (user_id, ip_address, log_date, action)
            select user_id, ip, log_date, action
            from xf_ip
            where user_id = ? and action like 'login%' and action != 'cookie_login'
        ", [$id]);

        if (\XF::isAddOnActive('SV/IpUsageSummary'))
        {
            \XF::db()->query("
                insert into xf_sv_login_log (user_id, ip_address, log_date, action)
                select user_id, ip, log_date, action
                from xf_ip_user_login
                where user_id = ? and action != 'cookie_login'
            ", [$id]);
        }
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('users');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

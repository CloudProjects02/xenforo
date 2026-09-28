<?php

namespace SV\SignupAbuseBlocking\Job;

use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;
use XF\Util\Ip;
use function array_key_exists;
use function is_string;

class EnrichLoginRecords extends AbstractRebuildJob
{
    /** @var array<string, int> */
    protected $seen = [];

    protected function getNextIds($start, $batch): array
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit('
				SELECT login_log_id
				FROM xf_sv_login_log
				WHERE login_log_id > ? AND (asn IS NULL OR country IS NULL)
				ORDER BY login_log_id
			', $batch
        ), [$start]);
    }

    protected function rebuildById($id): void
    {
        $db = \XF::db();
        $ip = $db->fetchOne('select ip_address from xf_sv_login_log where login_log_id = ? AND (asn IS NULL OR country IS NULL)', $id);
        if (!$ip)
        {
            return;
        }

        $ipAddress = Ip::convertIpBinaryToString($ip);
        if (!is_string($ipAddress))
        {
            return;
        }
        $result = UserRegistrationLogRepo::get()->clampStringIpToMinimumCIDR($ipAddress);
        if ($result === null)
        {
            return;
        }
        [$ipAddress, $minIp, $maxIp] = $result;

        if (array_key_exists($ipAddress, $this->seen))
        {
            return;
        }
        $this->seen[$ipAddress] = true;

        [$asn, $country] = UserRegistrationLogRepo::get()->resolveIpToAsnAndCountry($ipAddress, 0, false);
        if ($asn !== null)
        {
            $db->query('
                UPDATE xf_sv_login_log
                SET asn = ?
                WHERE ip_address >= ? AND ip_address <= ? AND asn IS NULL
            ', [$asn, $minIp, $maxIp]);
        }
        if ($country !== null)
        {
            $db->query('
                UPDATE xf_sv_login_log
                SET country = ?
                WHERE ip_address >= ? AND ip_address <= ? AND country IS NULL
            ', [$country, $minIp, $maxIp]);
        }
    }

    protected function getStatusType(): Phrase
    {
        return \XF::phrase('svSignupAbuseBlocking_rebuild_job_user_login');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

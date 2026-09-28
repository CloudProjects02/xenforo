<?php

namespace SV\SignupAbuseBlocking\Repository;

use SV\StandardLib\Helper;
use XF\Mvc\Entity\Repository;

class UserAgent extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function logUserAgent(?string $userAgent): ?int
    {
        if ($userAgent === null)
        {
            return null;
        }

        $db = $this->db();
        $hash = $this->hashUserAgent($userAgent);

        $userAgentId = (int)$db->fetchOne('
            SELECT user_agent_id 
            FROM xf_sv_user_agent 
            WHERE user_agent_hash = ? AND user_agent = ? 
            LIMIT 1
        ', [$hash, $userAgent]);
        if ($userAgentId !== 0)
        {
            return $userAgentId;
        }

        $db->insert('xf_sv_user_agent', [
            'user_agent'      => $userAgent,
            'user_agent_hash' => $hash,
            'first_log_date'  => \XF::$time,
            'last_log_date'   => \XF::$time,
            'count'           => 1,
        ], false, '
            last_log_date = GREATEST(last_log_date, values(last_log_date))
            , count = count + 1
        ');

        $id = (int)$db->lastInsertId();
        if ($id === 0)
        {
            $id = (int)$db->fetchOne('
                SELECT user_agent_id 
                FROM xf_sv_user_agent 
                WHERE user_agent_hash = ? AND user_agent = ? 
                LIMIT 1
            ', [$hash, $userAgent]);
            if ($id === 0)
            {
                return null;
            }
        }

        return $id;
    }

    public function hashUserAgent(?string $userAgent): string
    {
        return hash('sha256', $userAgent, true);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

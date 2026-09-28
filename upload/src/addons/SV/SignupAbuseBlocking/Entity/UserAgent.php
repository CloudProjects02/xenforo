<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\Repository\UserAgent as UserAgentRepo;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property ?int $user_agent_id
 * @property string $user_agent
 * @property string $user_agent_hash
 * @property int $first_log_date
 * @property int $last_log_date
 * @property int $count
 */
class UserAgent extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    protected function _preSave(): void
    {
        parent::_preSave();

        if ($this->user_agent_hash === null)
        {
            $this->user_agent_hash = UserAgentRepo::get()->hashUserAgent($this->user_agent);
        }
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_user_agent';
        $structure->shortName = 'SV\SignupAbuseBlocking:UserAgent';
        $structure->primaryKey = 'user_agent_id';
        $structure->columns = [
            'user_agent_id'   => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'user_agent'      => ['type' => self::BINARY, 'required' => true],
            'user_agent_hash' => ['type' => self::BINARY, 'maxLength' => 32, 'required' => true],
            'first_log_date'  => ['type' => self::UINT, 'default' => \XF::$time],
            'last_log_date'   => ['type' => self::UINT, 'default' => \XF::$time],
            'count'           => ['type' => self::UINT, 'default' => 0],
        ];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

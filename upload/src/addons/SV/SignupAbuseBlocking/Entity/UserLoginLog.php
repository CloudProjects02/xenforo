<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\Repository\UserRegistrationLog as UserRegistrationLogRepo;
use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Ip;
use function is_array;
use function strlen;

/**
 * COLUMNS
 *
 * @property int|null        $login_log_id
 * @property int             $user_id
 * @property string           $ip_address
 * @property int|null         $asn
 * @property string|null      $country
 * @property int|null         $user_agent_id
 * @property int              $log_date
 * @property string           $action
 * GETTERS
 * @property-read ?string     $user_agent
 * @property-read ?string     $asn_link
 * RELATIONS
 * @property-read ?UserEntity $User
 * @property-read ?UserAgent  $UserAgent
 */
class UserLoginLog extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    protected function getUserAgent():?string
    {
        return $this->UserAgent->user_agent ?? null;
    }

    protected function getAsnLink(): string
    {
        if (!$this->asn)
        {
            return '';
        }

        return UserRegistrationLogRepo::get()->getAsnLink($this->asn);
    }

    /** @noinspection PhpDeprecationInspection */
    protected function getIpAddressDisplay(): string
    {
        $ip = $this->ip_address;
        if (strlen($ip) === 16)
        {
            // truncate to a /64
            $ip = Ip::getIpCidrMatchRange($ip, 64);
            if (is_array($ip))
            {
                $ip = $ip[0];
            }
        }

        return Ip::convertIpBinaryToString($ip);
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_login_log';
        $structure->shortName = 'SV\SignupAbuseBlocking:UserLoginLog';
        $structure->primaryKey = 'login_log_id';
        $structure->columns = [
            'login_log_id'  => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'log_date'      => ['type' => self::UINT, 'default' => \XF::$time],
            'user_id'       => ['type' => self::UINT, 'required' => true],
            'action'        => ['type' => self::BINARY, 'default' => 'login'],
            'ip_address'    => ['type' => self::BINARY, 'maxLength' => 16],
            'asn'           => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'country'       => ['type' => self::STR, 'maxLength' => 3, 'nullable' => true, 'default' => null],
            'user_agent_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
        ];
        $structure->getters = [
            'user_agent' => ['getter' => 'getUserAgent', 'cache' => true],
            'asn_link' => ['getter' => 'getAsnLink', 'cache' => true],
            'ip_address_display' => ['getter' => 'getIpAddressDisplay', 'cache' => true],
        ];
        $structure->relations = [
            'User'      => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'UserAgent' => [
                'entity'     => 'SV\SignupAbuseBlocking:UserAgent',
                'type'       => self::TO_ONE,
                'conditions' => 'user_agent_id',
                'primary'    => true,
            ],
        ];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

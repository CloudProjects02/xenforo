<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\StandardLib\Helper;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Random;

/**
 * COLUMNS
 *
 * @property int|null         $token_id
 * @property int|null         $user_id
 * @property string|null      $token
 * @property int              $token_date
 * @property bool|null        $active
 * RELATIONS
 * @property-read ?UserEntity $User
 */
class Token extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    /** @var int */
    protected static $counter = 0;

    public function generateToken(?string $seed = null)
    {
        if ($this->token === null)
        {
            if ($seed === null)
            {
                if ($this->user_id)
                {
                    $seed = $this->user_id;
                }
                else
                {
                    ++self::$counter;
                    $seed = Random::getRandomString('32') . '' . self::$counter;
                }
            }
            $this->token = \hash_hmac('md5', $seed, $this->app()->config('globalSalt'));
        }
    }

    protected function _preSave()
    {
        if ($this->isInsert() && $this->token === null)
        {
            $this->generateToken();
        }
        if (!$this->active)
        {
            $this->active = null;
        }
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_multiple_account_token';
        $structure->shortName = 'SV\SignupAbuseBlocking:Token';
        $structure->primaryKey = 'token_id';
        $structure->columns = [
            'token_id'   => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'user_id'    => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'token'      => ['type' => self::STR, 'maxLength' => 32, 'nullable' => true, 'default' => null],
            'token_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'active'     => ['type' => self::BOOL, 'nullable' => true, 'default' => null],
        ];
        $structure->relations = [
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ]
        ];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

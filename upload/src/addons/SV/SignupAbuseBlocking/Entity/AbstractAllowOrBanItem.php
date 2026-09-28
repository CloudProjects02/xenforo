<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\Repository\AbstractAllowOrBanItem as ItemRepo;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class AbstractAllowOrBanItem
 *
 * @package SV\SignupAbuseBlocking\Entity
 * COLUMNS
 * @property int                      $create_user_id
 * @property int                      $create_date
 * @property string                   $reason
 * @property int                      $last_triggered_date
 * @property int                      $trigger_count
 * RELATIONS
 * @property-read ?ExtendedUserEntity $User
 */
abstract class AbstractAllowOrBanItem extends Entity
{
    protected function getRunOnceKey(): string
    {
        return 'svSignupAbuseBlocking_' . $this->structure()->shortName;
    }

    protected function rebuildItemCache()
    {
        \XF::runOnce($this->getRunOnceKey(), function ()
        {
            $this->getItemRepo()->rebuildItemCache();
        });
    }

    protected function _postSave()
    {
        parent::_postSave();
        $this->rebuildItemCache();
    }

    protected function _postDelete()
    {
        parent::_postDelete();
        $this->rebuildItemCache();
    }

    protected function getItemRepo(): ItemRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->app()->repository($this->structure()->shortName);
    }

    protected static function setupDefaultStructure(Structure $structure, string $shortName, string $table, string $primaryKey)
    {
        $structure->shortName = $shortName;
        $structure->table = $table;
        $structure->primaryKey = $primaryKey;
        $structure->columns = [
            $primaryKey => ['type' => static::STR, 'required' => true, 'maxLength' => 120],
            'create_user_id' => ['type' => static::UINT, 'required' => true],
            'create_date' => ['type' => static::UINT, 'default' => \XF::$time],
            'reason' => ['type' => static::STR, 'maxLength' => 255, 'default' => ''],
            'last_triggered_date' => ['type' => static::UINT, 'default' => 0],
            'trigger_count' => ['type' => static::UINT, 'default' => 0]
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => static::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$create_user_id']
                ],
                'primary' => true
            ]
        ];
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

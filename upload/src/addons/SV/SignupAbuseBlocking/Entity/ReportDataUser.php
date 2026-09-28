<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 *
 * @property int                      $report_data_id
 * @property int                      $user_id
 * @property int                      $first_seen_date
 * @property int                      $last_seen_date
 * @property int                      $count
 * RELATIONS
 * @property-read ?ExtendedUserEntity $User
 * @property-read ?MultiAccountUser   $MultiAccountUser
 * @property-read ?ReportData         $ReportData
 */
class ReportDataUser extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_multiple_account_report_data_user';
        $structure->shortName = 'SV\SignupAbuseBlocking:ReportDataUser';
        $structure->primaryKey = ['report_data_id', 'user_id'];
        $structure->columns = [
            'report_data_id'  => ['type' => self::UINT, 'required' => true],
            'user_id'         => ['type' => self::UINT, 'required' => true],
            'first_seen_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'last_seen_date'  => ['type' => self::UINT, 'default' => \XF::$time],
            'count'           => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->relations = [
            'User'             => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'MultiAccountUser' => [
                'entity'     => 'SV\SignupAbuseBlocking:MultiAccountUser',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'ReportData'       => [
                'entity'     => 'SV\SignupAbuseBlocking:ReportData',
                'type'       => self::TO_ONE,
                'conditions' => 'report_data_id',
                'primary'    => true
            ],
            'OtherUsers' => [
                'entity'     => 'SV\SignupAbuseBlocking:ReportDataUser',
                'type'       => self::TO_MANY,
                'conditions' => [
                    ['report_data_id', '==', '$report_data_id'],
                    ['user_id', '!=', '$user_id'],
                ]
            ],
        ];

        $structure->defaultWith = [];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 

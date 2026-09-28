<?php

namespace XenDev\StaffManager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class StaffBlock extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xendev_staff_block';
        $structure->shortName = 'XenDev\StaffManager:StaffBlock';
        $structure->primaryKey = 'block_id';

        $structure->columns = [
            'block_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'title' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'source_type' => [
                'type' => self::STR,
                'default' => 'user_group',
                'allowedValues' => ['user_group', 'admin', 'global_mod', 'node_mod']
            ],
            'user_group_id' => ['type' => self::UINT, 'default' => 0],
            'node_id' => ['type' => self::UINT, 'default' => 0],
            'group_match_type' => [
                'type' => self::STR,
                'default' => 'both',
                'allowedValues' => ['primary', 'secondary', 'both']
            ],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'icon_class' => ['type' => self::STR, 'maxLength' => 50, 'default' => 'fa-users'],
            'active' => ['type' => self::BOOL, 'default' => true],
        ];

        $structure->relations = [
            'UserGroup' => [
                'entity' => 'XF:UserGroup',
                'type' => self::TO_ONE,
                'conditions' => 'user_group_id',
                'primary' => true
            ],
            'Node' => [
                'entity' => 'XF:Node',
                'type' => self::TO_ONE,
                'conditions' => 'node_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
 		  					 	  		   	  			   	  			  	    	 				     		 			 		 		 	  			     	  	 		 			  	   	   	 		  	    		  			      	      		  

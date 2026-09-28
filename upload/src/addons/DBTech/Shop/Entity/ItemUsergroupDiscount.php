<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $item_id
 * @property int $user_group_id
 * @property string $discount_type
 * @property float $discount_value
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Item $Item
 * @property \XF\Entity\UserGroup $UserGroup
 */
class ItemUsergroupDiscount extends Entity
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_dbtech_shop_item_usergroup_discount';
        $structure->shortName = 'DBTech\Shop:ItemUsergroupDiscount';
        $structure->primaryKey = ['item_id', 'user_group_id'];
        
        $structure->columns = [
            'item_id' => ['type' => self::UINT, 'required' => true],
            'user_group_id' => ['type' => self::UINT, 'required' => true],
            'discount_type' => ['type' => self::STR, 'allowedValues' => ['percentage', 'fixed'], 'default' => 'percentage'],
            'discount_value' => ['type' => self::FLOAT, 'min' => 0, 'default' => 0]
        ];
        
        $structure->relations = [
            'Item' => [
                'entity' => 'DBTech\Shop:Item',
                'type' => self::TO_ONE,
                'conditions' => 'item_id',
                'primary' => true
            ],
            'UserGroup' => [
                'entity' => 'XF:UserGroup',
                'type' => self::TO_ONE,
                'conditions' => 'user_group_id',
                'primary' => true
            ]
        ];
        
        return $structure;
    }
}
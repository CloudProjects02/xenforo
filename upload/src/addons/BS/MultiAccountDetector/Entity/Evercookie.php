<?php

namespace BS\MultiAccountDetector\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int evercookie_id
 * @property string evercookie
 * @property int user_id
 * @property int evercookie_date
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class Evercookie extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_mad_user_evercookie';
        $structure->shortName = 'BS\MultiAccountDetector:Evercookie';
        $structure->primaryKey = 'evercookie_id';
        $structure->columns = [
            'evercookie_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'evercookie' => ['type' => self::BINARY, 'required' => true, 'maxLength' => 32],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'evercookie_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
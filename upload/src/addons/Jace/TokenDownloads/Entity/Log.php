<?php

namespace Jace\TokenDownloads\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $log_id
 * @property int $user_id
 * @property int $purchase_id
 * @property int $resource_id
 * @property int $resource_version_id
 * @property int $log_date
 * 
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \Jace\TokenDownloads\Entity\Purchase $Purchase
 * @property \XFRM\Entity\ResourceItem $Resource
 * @property \XFRM\Entity\ResourceVersion $ResourceVersion
 */
class Log extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'jace_token_log';
        $structure->shortName = 'Jace\TokenDownloads:Log';
        $structure->primaryKey = 'log_id';
        $structure->columns = [
            'log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'purchase_id' => ['type' => self::UINT, 'required' => true],
            'resource_id' => ['type' => self::UINT, 'required' => true],
            'resource_version_id' => ['type' => self::UINT, 'required' => true],
            'log_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        $structure->getters = [];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Purchase' => [
                'entity' => 'Jace\TokenDownloads:Purchase',
                'type' => self::TO_ONE,
                'conditions' => 'purchase_id',
                'primary' => true
            ],
            'Resource' => [
                'entity' => 'XFRM:ResourceItem',
                'type' => self::TO_ONE,
                'conditions' => 'resource_id',
                'primary' => true
            ],
            'ResourceVersion' => [
                'entity' => 'XFRM:ResourceVersion',
                'type' => self::TO_ONE,
                'conditions' => 'resource_version_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
} 
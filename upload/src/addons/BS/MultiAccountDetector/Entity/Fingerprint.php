<?php

namespace BS\MultiAccountDetector\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int fingerprint_id
 * @property string fingerprint
 * @property int user_id
 * @property int fingerprint_date
 * @property bool $is_pro
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class Fingerprint extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_mad_user_fingerprint';
        $structure->shortName = 'BS\MultiAccountDetector:Fingerprint';
        $structure->primaryKey = 'fingerprint_id';
        $structure->columns = [
            'fingerprint_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'fingerprint' => ['type' => self::BINARY, 'required' => true, 'maxLength' => 40],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'fingerprint_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'is_pro' => [
                'type' => self::BOOL,
                'default' => ! empty(\XF::options()->madFingerprintProToken)
            ]
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
<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class AwardBadge extends Entity {

    public static function getStructure(Structure $structure) {


        $structure->table = 'xc_award_user_badge';
        $structure->shortName = 'XC\RankingSystem:AwardBadge';
        $structure->primaryKey = 'award_badge_id';
        
        $structure->columns = [
            'award_badge_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'badge_id' => ['type' => self::UINT, 'default' => 0],
            'award_date' => ['type' => self::UINT, 'default' => 0],
            'manual' => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->relations = [
            'Badge' => [
                'entity' => 'XC\RankingSystem:Badge',
                'type' => self::TO_ONE,
                'conditions' => 'badge_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
			'AwardBadges' => [
                'entity' => 'XC\RankingSystem:AwardBadge',
                'type' => self::TO_MANY,
                'conditions' => 'user_id',
            ],
        ];

        return $structure;
    }

}

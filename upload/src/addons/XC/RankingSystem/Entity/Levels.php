<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class Levels extends Entity {

    public static function getStructure(Structure $structure) {


        $structure->table = 'xc_ranking_levels';
        $structure->shortName = 'XC\RankingSystem:Levels';
        $structure->primaryKey = 'level_id';
        $structure->columns = [
            'level_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'level' => ['type' => self::UINT, 'default' => 0],
            'exp_points' => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->relations = [
            'LevelBadge' => [
                'entity' => 'XC\RankingSystem:LevelBadge',
                'type' => self::TO_ONE,
                'conditions' => 'level_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

}

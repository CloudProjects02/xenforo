<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class ExperiencePoint extends Entity {

    public static function getStructure(Structure $structure) {


        $structure->table = 'xc_experience_point';
        $structure->shortName = 'XC\RankingSystem:ExperiencePoint';
        $structure->primaryKey = 'xp_id';
        $structure->contentType = 'experience_point';
        $structure->columns = [
            'xp_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'points' => ['type' => self::UINT, 'default' => 0],
            'point_type' => ['type' => self::STR,],
            'point_depend' => ['type' => self::UINT, 'default' => 0]
        ];

        return $structure;
    }

}

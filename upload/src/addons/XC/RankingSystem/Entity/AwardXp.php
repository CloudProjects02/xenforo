<?php

namespace XC\RankingSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class AwardXp extends Entity {

    public static function getStructure(Structure $structure) {


        $structure->table = 'xc_user_xp';
        $structure->shortName = 'XC\RankingSystem:AwardXp';
        $structure->primaryKey = 'award_xp_id';
        $structure->contentType = 'award_xp';
        $structure->columns = [
          
            'award_xp_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'xp_id' => ['type' => self::UINT, 'default' => 0],
            'award_date' => ['type' => self::UINT, 'default' => 0],
            'manual' => ['type' => self::UINT, 'default' => 0],
            'content_type' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'content_id' => ['type' => self::UINT, 'default' => 0],
            'spot_ex_point' => ['type' => self::UINT, 'default' => 0],
            
        ];
        
          $structure->relations = [
            'Xp' => [
                'entity' => 'XC\RankingSystem:ExperiencePoint',
                'type' => self::TO_ONE,
                'conditions' => 'xp_id',
                'primary' => true
            ],
              'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
        ];
         

        return $structure;
    }

}

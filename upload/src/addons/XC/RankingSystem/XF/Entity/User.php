<?php

namespace XC\RankingSystem\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User {

    public static function getStructure(Structure $structure) {

        $structure = parent::getStructure($structure);

        $structure->columns['total_points'] = ['type' => self::INT, 'default' => 0];

        $structure->columns['level'] = ['type' => self::INT, 'default' => 0];
        $structure->columns['first_visit'] = ['type' => self::INT, 'default' => 0];
        $structure->columns['latest_visit'] = ['type' => self::INT, 'default' => 0];
		$structure->relations['AwardBadges'] = [
            'entity' => 'XC\RankingSystem:AwardBadge',
            'type' => self::TO_MANY,
            'conditions' => 'user_id',
        ];
        return $structure;
    }
  
    public function getBadgeCount()
    {
        return $this->AwardBadges->count();
    }

    public function getcommaFormatPoints() {

        return \XF::language()->numberFormat($this->total_points);
    }
}

<?php

namespace XC\RankingSystem\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Thread extends XFCP_Thread {

    public static function getStructure(Structure $structure) {

        $structure = parent::getStructure($structure);

        $structure->columns['rank_reply_count'] = ['type' => self::INT, 'default' => 0];

        return $structure;
    }

}

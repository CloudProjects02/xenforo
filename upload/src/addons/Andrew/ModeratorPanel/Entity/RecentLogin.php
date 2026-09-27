<?php

namespace Andrew\ModeratorPanel\Entity;

use XF\Mvc\Entity\Structure;


class RecentLogin extends \XF\Mvc\Entity\Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_andrew_mp_recent_login';
        $structure->shortName = 'Andrew\ModeratorPanel:RecentLogin';
        $structure->primaryKey = 'login_id';
        $structure->contentType = 'andrew_mp_recent_login';
        $structure->columns = [
            'login_id' => ['type' => self::UINT, 'autoIncrement' => true, 'changeLog' => false],
            'user_id' => ['type' => self::UINT, 'changeLog' => false],
            'ip' => ['type' => self::STR, 'changeLog' => false],
            'country' => ['type' => self::STR, 'changeLog' => false],
            'login_date' => ['type' => self::UINT, 'default' => \XF::$time, 'changeLog' => false],
        ];
        $structure->getters = [
        ];
        $structure->behaviors = [

        ];
        $structure->options = [
            'log_moderator' => true
        ];
        $structure->relations = [
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
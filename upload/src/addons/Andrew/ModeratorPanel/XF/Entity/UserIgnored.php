<?php

namespace Andrew\ModeratorPanel\XF\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;


class UserIgnored extends XFCP_UserIgnored
{

    protected function _preDelete()
    {
        $visitor = \XF::visitor();
        $forced = $this->em()->findOne('XF:UserIgnored', [
            'user_id' => $this->user_id,
            'ignored_user_id' => $this->ignored_user_id,
            'andrew_forced' => 1
        ]);

        if ($forced and !$visitor->canForceIgnoreMP())
        {
            $this->error(\XF::phrase('andrew_moderatorpanel_you_do_not_have_permission_to_remove_this_ignore'));
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['andrew_forced'] = [
            'type' => self::UINT,
            'default' => 0,
            'changeLog' => false,
            'api' => true
        ];
        $structure->columns['andrew_forced_user_id'] = [
            'type' => self::UINT,
            'default' => 0,
            'changeLog' => false,
            'api' => true
        ];
        $structure->columns['andrew_forced_datetime'] = [
            'type' => self::UINT,
            'default' => \XF::$time,
            'changeLog' => false,
            'api' => true
        ];
        $structure->relations['IgnoredUser'] = [
            'entity' => 'XF:User',
            'type' => self::TO_ONE,
            'conditions' => [['user_id', '=', '$ignored_user_id']],
            'primary' => true
        ];
        $structure->relations['IgnoredBy'] = [
            'entity' => 'XF:User',
            'type' => self::TO_ONE,
            'conditions' => [['user_id', '=', '$user_id']],
            'primary' => true
        ];
        $structure->relations['ForcedBy'] = [
            'entity' => 'XF:User',
            'type' => self::TO_ONE,
            'conditions' => [['user_id', '=', '$andrew_forced_user_id']],
            'primary' => true
        ];


        return $structure;
    }

}
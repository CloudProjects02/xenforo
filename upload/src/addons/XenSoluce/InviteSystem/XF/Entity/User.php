<?php

namespace XenSoluce\InviteSystem\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);
        $structure->columns['xs_is_invite_count'] = ['type' => self::UINT, 'default' => 0, 'forced' => true, 'changeLog' => false];
        $structure->relations += [
            'InviteUser' => [
                'entity' => 'XenSoluce\InviteSystem:CodeInvitation',
                'type' => self::TO_ONE,
                'conditions' => [['registered_user_id', '=', '$user_id']],
            ],
            'InviteBan' => [
                'entity' => 'XenSoluce\InviteSystem:Banning',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
            ]
        ];

        return $structure;
    }
}

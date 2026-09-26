<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

class Token extends Entity
{
    protected function _preSave()
    {
        if(empty($this->token))
        {
            $this->token = \XF::generateRandomString(32);
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_token';
        $structure->shortName  = 'XenSoluce\InviteSystem:Token';
        $structure->primaryKey = 'token_id';

        $structure->columns = [
            'token_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'token' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'user' => ['type' => self::LIST_COMMA],
            'type_token' => ['type' => self::UINT, 'required' => true],
            'number_use' =>  ['type' => self::UINT, 'required' => true, 'default' => 1]
        ];
        $structure->relations = [
            'Code' => [
                'entity'     => 'XenSoluce\InviteSystem:CodeInvitation',
                'type'       => self::TO_ONE,
                'conditions' => 'token_id',
            ],
            'Codes' => [
                'entity'     => 'XenSoluce\InviteSystem:CodeInvitation',
                'type'       => self::TO_MANY,
                'conditions' => 'token_id',
            ],
        ];

        return $structure;
    }
}
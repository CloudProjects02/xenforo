<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null view_id
 * @property int visitor_user_id
 * @property int profile_user_id
 * @property int view_date
 *
 * RELATIONS
 * @property \XF\Entity\User Visitor
 * @property \XF\Entity\User ProfileUser
 */
class ProfileViews extends Entity
{

    protected function _postSave()
    {
        if ($this->isInsert())
        {
            $this->adjustUserProfileViewCountIfNeeded(1);
        }

        if ($this->isUpdate() && $this->isChanged('view_date'))
        {
            $this->adjustUserProfileViewCountIfNeeded(1);
        }
    }

    protected function adjustUserProfileViewCountIfNeeded($amount, $profileUserId = null)
    {
        if ($profileUserId === null)
        {
            $profileUserId = $this->profile_user_id;
        }

        if ($profileUserId)
        {
            $this->db()->query("
				UPDATE xf_user
				SET xc_pv_profile_view_count = GREATEST(0, xc_pv_profile_view_count + ?)
				WHERE user_id = ?
			", [$amount, $profileUserId]);
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xc_profile_views';
        $structure->shortName  = 'XenConcept\ProfileViews:ProfileViews';
        $structure->primaryKey = 'view_id';

        $structure->columns = [
            'view_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'visitor_user_id' => ['type' => self::UINT, 'required' => true],
            'profile_user_id' => ['type' => self::UINT, 'required' => true],
            'view_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];
        $structure->getters = [];
        $structure->relations = [
            'Visitor' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$visitor_user_id']
                ]
            ],
            'ProfileUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$profile_user_id']
                ]
            ]
        ];

        return $structure;
    }

}
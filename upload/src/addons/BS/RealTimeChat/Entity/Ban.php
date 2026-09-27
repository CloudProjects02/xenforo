<?php

namespace BS\RealTimeChat\Entity;

use BS\RealTimeChat\Broadcasting\Broadcast;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $ban_id
 * @property int $user_id
 * @property int|null $room_id
 * @property int $ban_user_id
 * @property int $date
 * @property int $unban_date
 * @property string $reason
 *
 * GETTERS
 * @property mixed $formatted_unban_date
 * @property mixed $ban_title
 *
 * RELATIONS
 * @property \BS\RealTimeChat\Entity\Room $Room
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $BanUser
 */

class Ban extends Entity
{
    public function hasReason()
    {
        return ! empty($this->reason);
    }

    public function getFormattedUnbanDate()
    {
        return $this->unban_date > 0
            ? \XF::language()->dateTime($this->unban_date)
            : \XF::phrase('never');
    }

    public function getBanTitle()
    {
        if (! $this->hasReason()) {
            return \XF::phrase('rtc_you_have_been_banned_from_this_room', [
                'unban_date' => $this->formatted_unban_date
            ]);
        }

        return \XF::phrase('rtc_you_have_been_banned_from_this_room_with_reason', [
            'unban_date' => $this->formatted_unban_date,
            'reason' => $this->reason
        ]);
    }

    protected function _postSave()
    {
        if ($this->isInsert()) {
            Broadcast::wasBanned($this->User, $this->Room->tag);
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_chat_ban';
        $structure->shortName = 'BS\RealTimeChat:Ban';
        $structure->primaryKey = 'ban_id';
        $structure->columns = [
            'ban_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'room_id' => ['type' => self::UINT, 'nullable' => true],
            'ban_user_id' => ['type' => self::UINT, 'required' => true, 'default' => \XF::visitor()->user_id],
            'date' => ['type' => self::UINT, 'default' => \XF::$time],
            'unban_date' => ['type' => self::UINT, 'default' => 0],
            'reason' => ['type' => self::STR, 'maxLength' => 300, 'required' => false]
        ];
        $structure->getters = [
            'formatted_unban_date' => false,
            'ban_title' => true
        ];
        $structure->relations = [
            'Room' => [
                'entity' => 'BS\RealTimeChat:Room',
                'type' => self::TO_ONE,
                'conditions' => 'room_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id'
            ],
            'BanUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$ban_user_id']]
            ]
        ];

        return $structure;
    }
}

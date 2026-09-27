<?php

namespace BS\RealTimeChat\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $link_id
 * @property int $room_id
 * @property int|null $user_id
 * @property int $create_date
 * @property int|null $expire_date
 * @property int $usage_count
 *
 * GETTERS
 * @property mixed $url
 *
 * RELATIONS
 * @property \BS\RealTimeChat\Entity\Room $Room
 */
class RoomLink extends Entity
{
    public function join(\XF\Entity\User $user)
    {
        /** @var \BS\RealTimeChat\Service\Room\Join $joiner */
        $joiner = $this->app()->service(
            'BS\RealTimeChat:Room\Join',
            $this->Room,
            $user
        );
        $joiner->joinFromLink($this);
    }

    protected function _preSave()
    {
        if (! $this->link_id) {
            $this->link_id = \XF::generateRandomString(16);
        }
    }

    public function getUrl()
    {
        return $this->app()->router('public')->buildLink('canonical:chat/l', $this);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_chat_room_link';
        $structure->shortName = 'BS\RealTimeChat:RoomLink';
        $structure->primaryKey = 'link_id';
        $structure->columns = [
            'link_id' => ['type' => self::STR, 'autoIncrement' => false],
            'room_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'nullable' => true],
            'create_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'expire_date' => ['type' => self::UINT, 'nullable' => true],
            'usage_count' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [
            'url' => true
        ];
        $structure->relations = [
            'Room' => [
                'entity' => 'BS\RealTimeChat:Room',
                'type' => self::TO_ONE,
                'conditions' => 'room_id',
                'primary' => true
            ],
        ];

        return $structure;
    }
}

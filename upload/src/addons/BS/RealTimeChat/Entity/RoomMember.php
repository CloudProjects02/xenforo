<?php

namespace BS\RealTimeChat\Entity;

use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\DB;
use BS\RealTimeChat\Entity\Concerns\UpdateLockable;
use BS\RealTimeChat\Job\RoomMember\TouchLastReply;
use BS\RealTimeChat\Job\RoomMember\TouchLastView;
use BS\RealTimeChat\Utils\Date;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $member_id
 * @property int $room_id
 * @property int $user_id
 * @property int|null $invited_by_user_id
 * @property string $invite_type
 * @property int $join_date
 * @property int $last_reply_date
 * @property int $last_view_date
 * @property bool $room_pinned
 * @property int $room_pin_order
 * @property int $room_wallpaper_date
 * @property array|null $room_wallpaper_options
 * @property int $unread_count
 *
 * RELATIONS
 * @property \BS\RealTimeChat\Entity\Room $Room
 * @property \XF\Entity\User $User
 */
class RoomMember extends Entity
{
    use UpdateLockable;

    public function touchLastReplyDate(?int $date = null)
    {
        if ($this->isDeleted()) {
            return;
        }

        TouchLastReply::create($this->room_id, $this->user_id, $date);
    }

    public function touchLastViewDate(?int $date = null)
    {
        if ($this->isDeleted()) {
            return;
        }

        TouchLastView::create($this->room_id, $this->user_id, $date);
    }

    protected function _postSave()
    {
        if ($this->isInsert()) {
            $this->getRoomRepo()->updateMembers($this->Room);
        }

        if (! $this->isUpdate()) {
            return;
        }

        if ($this->isOneOfBroadcastColumnsChanged()) {
            $this->Room->broadcastUpdate();
        }

        if ($this->isChanged('last_view_date')) {
            Broadcast::roomHasBeenRead($this);

            $user = $this->User;
            if (! $user) {
                $user = $this->em()->find('XF:User', $this->user_id);
            }
            if (! $user) {
                return;
            }

            $this->getMessageRepo()->markReadMessagesBeforeDate(
                $this->Room,
                $user,
                $this->last_view_date
            );

            $this->getRoomRepo()
                ->updateMembersUnreadCount($this->room_id);
        }
    }

    protected function isOneOfBroadcastColumnsChanged()
    {
        $broadcastColumns = $this->getBroadcastColumns();
        foreach ($broadcastColumns as $column) {
            if ($this->isChanged($column)) {
                return true;
            }
        }

        return false;
    }

    protected function getBroadcastColumns()
    {
        return [
            'room_wallpaper_date',
            'room_wallpaper_options',
        ];
    }

    protected function _postDelete()
    {
        if (! $this->User || ! $this->Room) {
            return;
        }

        $this->getRoomRepo()->updateMembers($this->Room);

        Broadcast::roomsDeleted([
            'user' => $this->User,
        ], [
            'tags' => [$this->Room->tag]
        ]);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_chat_room_member';
        $structure->shortName = 'BS\RealTimeChat:RoomMember';
        $structure->primaryKey = 'member_id';
        $structure->columns = [
            'member_id'              => ['type' => self::UINT, 'autoIncrement' => true],
            'room_id'                => ['type' => self::UINT, 'required' => true],
            'user_id'                => ['type' => self::UINT, 'required' => true],
            'invited_by_user_id'     => ['type' => self::UINT, 'nullable' => true],
            'invite_type'            => ['type' => self::STR, 'maxLength' => 25, 'default' => 'join'],
            'join_date'              => ['type' => self::UINT, 'default' => \XF::$time],
            'last_reply_date'        => ['type' => self::UINT, 'default' => 0, 'max' => pow(2, 63) - 2],
            'last_view_date'         => ['type' => self::UINT, 'default' => 0, 'max' => pow(2, 63) - 2],
            'room_pinned'            => ['type' => self::BOOL, 'default' => false],
            'room_pin_order'         => ['type' => self::UINT, 'default' => 0],
            'room_wallpaper_date'    => ['type' => self::UINT, 'default' => 0],
            'room_wallpaper_options' => ['type' => self::JSON_ARRAY, 'default' => null, 'nullable' => true],
            'unread_count'           => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->getters = [];
        $structure->relations = [
            'Room' => [
                'entity'     => 'BS\RealTimeChat:Room',
                'type'       => self::TO_ONE,
                'conditions' => 'room_id',
                'primary'    => true
            ],
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ]
        ];

        return $structure;
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\RealTimeChat\Repository\Room
     */
    protected function getRoomRepo()
    {
        return $this->repository('BS\RealTimeChat:Room');
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\RealTimeChat\Repository\Message
     */
    protected function getMessageRepo()
    {
        return $this->repository('BS\RealTimeChat:Message');
    }
}

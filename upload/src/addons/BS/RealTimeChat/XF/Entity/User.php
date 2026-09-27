<?php

namespace BS\RealTimeChat\XF\Entity;

use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    public function canViewChat()
    {
        return $this->hasChatPermission('canView');
    }

    public function canBanUserInChatRoom(Room $room, \XF\Entity\User $user)
    {
        if ($user->is_admin || $user->is_super_admin || $user->is_moderator) {
            return false;
        }

        if ($room->isBanned($user)) {
            return false;
        }

        if ($this->hasChatPermission('canBanAny')) {
            return true;
        }

        return $this->hasChatPermission('canBan')
            && $room->isOwner($this);
    }

    public function canLiftBanUserInChatRoom(Room $room, \XF\Entity\User $user)
    {
        if (! $room->isBanned($user)) {
            return false;
        }

        if ($this->hasChatPermission('canBanAny')) {
            return true;
        }

        return $this->hasChatPermission('canBan')
            && $room->isOwner($this);
    }

    public function canViewChatRoom($tag, ?string &$error = ''): bool
    {
        if (! $this->hasAccessToChatRoom($tag, $error)) {
            return false;
        }

        return $this->hasChatPermission('canView');
    }

    public function canCreateChatRoom()
    {
        return $this->hasChatPermission('createRoom');
    }

    public function hasAccessToChatRoom($tag, ?string &$error = ''): bool
    {
        $room = null;

        if ($tag instanceof Room) {
            $room = $tag;
            $tag = $room->tag;
        }

        if (! $this->canViewChat()) {
            return false;
        }

        /** @var \BS\RealTimeChat\Entity\Room $room */
        $room ??= $this->em()->findOne('BS\RealTimeChat:Room', compact('tag'));
        if (! $room) {
            $error = (string)\XF::phrase('rtc_requested_chat_room_not_found');
            return false;
        }

        if (! $room->canView($this, $error)) {
            $error ??= (string)\XF::phrase('rtc_you_have_not_access_to_this_chat_room');
            return false;
        }

        return true;
    }

    public function hasChatPermission($permission)
    {
        return $this->hasPermission('bsChat', $permission);
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['rtc_language_code'] = ['type' => self::STR, 'default' => ''];

        return $structure;
    }
}

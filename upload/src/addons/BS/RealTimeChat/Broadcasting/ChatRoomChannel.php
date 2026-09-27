<?php

namespace BS\RealTimeChat\Broadcasting;

use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Utils\RoomTag;
use BS\XFWebSockets\Broadcasting\PresenceChannel;

class ChatRoomChannel extends PresenceChannel
{
    public function __construct($tag)
    {
        parent::__construct('ChatRoom.' . RoomTag::urlEncode($tag));
    }

    public function join(\XF\Entity\User $visitor, $tag)
    {
        if (! $tag) {
            return false;
        }

        $tag = RoomTag::normalize($tag);

        return $visitor->canViewChatRoom($tag);
    }
}

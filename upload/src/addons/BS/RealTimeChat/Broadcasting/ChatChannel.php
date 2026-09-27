<?php

namespace BS\RealTimeChat\Broadcasting;

use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Utils\RoomTag;
use BS\XFWebSockets\Broadcasting\PresenceChannel;

class ChatChannel extends PresenceChannel
{
    public function __construct($name = '')
    {
        parent::__construct('Chat');
    }

    public function join(\XF\Entity\User $visitor)
    {
        return $visitor->canViewChat();
    }
}

<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Broadcasting\ChatChannel;
use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use BS\RealTimeChat\Broadcasting\Events\Concerns\ToRoomChannels;
use BS\RealTimeChat\Entity\Room;
use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;

class RoomUpdated implements Event
{
    use ToRoomChannels;

    public Room $room;

    public function __construct(Room $room)
    {
        $this->room = $room;
    }

    public function payload(): array
    {
        return [
            'room' => [
                'tag'   => $this->room->tag,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'RTC\RoomUpdated';
    }
}

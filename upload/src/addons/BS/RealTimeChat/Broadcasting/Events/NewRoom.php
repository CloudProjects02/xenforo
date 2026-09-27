<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Entity\Room;
use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;
use XF\Entity\User;

class NewRoom implements Event
{
    public User $user;
    public Room $room;

    public function __construct(User $user, Room $room)
    {
        $this->user = $user;
        $this->room = $room;
    }

    public function toChannels(): array
    {
        return [
            new UserChannel($this->user->user_id),
        ];
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
        return 'RTC\NewRoom';
    }
}
<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;
use XF\Entity\User;

class WasBanned implements Event
{
    public User $user;
    public string $roomTag;

    public function __construct(User $user, string $roomTag)
    {
        $this->user = $user;
        $this->roomTag = $roomTag;
    }

    public function toChannels(): array
    {
        return [
            new UserChannel($this->user->user_id)
        ];
    }

    public function payload(): array
    {
        return [
            'room_tag' => $this->roomTag,
        ];
    }

    public function broadcastAs(): string
    {
        return 'RTC\WasBanned';
    }
}
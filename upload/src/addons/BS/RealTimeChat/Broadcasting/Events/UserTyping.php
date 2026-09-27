<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use BS\XFWebSockets\Broadcasting\Event;
use XF\Entity\User;

class UserTyping implements Event
{
    public User $user;
    public string $roomTag;

    public int $delay;

    public function __construct(User $user, string $roomTag, int $delay = 4)
    {
        $this->user = $user;
        $this->roomTag = $roomTag;
        $this->delay = $delay;
    }

    public function toChannels(): array
    {
        return [
            new ChatRoomChannel($this->roomTag)
        ];
    }

    public function payload(): array
    {
        return [
            'user' => [
                'id'   => $this->user->user_id,
                'name' => $this->user->username,
            ],
            'room' => [
                'tag' => $this->roomTag,
            ],
            'delay' => $this->delay,
        ];
    }

    public function broadcastAs(): string
    {
        return 'RTC\UserTyping';
    }
}

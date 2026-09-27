<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use BS\XFWebSockets\Broadcasting\Event;

class MessagesDeleted implements Event
{
    public string $roomTag;

    public array $criteria;

    public function __construct(string $roomTag, array $criteria = [])
    {
        $this->roomTag = $roomTag;
        $this->criteria = $criteria;
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
            'criteria' => $this->criteria
        ];
    }

    public function broadcastAs(): string
    {
        return 'RTC\MessagesDeleted';
    }
}
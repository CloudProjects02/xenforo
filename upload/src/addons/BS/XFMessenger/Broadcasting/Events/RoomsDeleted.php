<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;

class RoomsDeleted implements Event
{
    public array $criteria;

    public array $recipientIds;

    public function __construct(array $criteria, array $recipientIds)
    {
        $this->criteria = $criteria;
        $this->recipientIds = $recipientIds;
    }

    public function toChannels(): array
    {
        $channels = [];

        foreach ($this->recipientIds as $recipientId) {
            $channels[] = new UserChannel($recipientId);
        }

        return $channels;
    }

    public function payload(): array
    {
        return [
            'criteria' => $this->criteria
        ];
    }

    public function broadcastAs(): string
    {
        return 'XFM\RoomsDeleted';
    }
}
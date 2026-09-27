<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\XFMessenger\Broadcasting\Events\Concerns\ToConversationChannels;
use BS\XFWebSockets\Broadcasting\Event;
use XF\Entity\ConversationMaster;

class RoomUpdated implements Event
{
    use ToConversationChannels;

    public ConversationMaster $conversation;

    public array $recipientIds = [];

    public function __construct(ConversationMaster $conversation, array $recipientIds = [])
    {
        $this->conversation = $conversation;
        $this->recipientIds = $recipientIds;
    }

    public function payload(): array
    {
        return [
            'room' => [
                'tag'   => (string)$this->conversation->conversation_id,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'XFM\RoomUpdated';
    }
}

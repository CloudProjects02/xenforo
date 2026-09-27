<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\XFMessenger\Broadcasting\Events\Concerns\ToConversationChannels;
use XF\Entity\ConversationMaster;
use BS\XFWebSockets\Broadcasting\Event;
use XF\Entity\ConversationRecipient;

class RoomHasBeenRead implements Event
{
    use ToConversationChannels;

    public ConversationMaster $conversation;

    public ConversationRecipient $recipient;

    public array $excludeRecipientIds = [];

    public function __construct(ConversationRecipient $recipient)
    {
        $this->conversation = $recipient->Conversation;
        $this->recipient = $recipient;
        $this->excludeRecipientIds = [$recipient->user_id];
    }

    public function payload(): array
    {
        return [
            'room' => [
                'tag' => (string)$this->conversation->conversation_id,
            ],
            'user' => [
                'id'        => $this->recipient->user_id,
                'read_date' => $this->recipient->last_read_date,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'XFM\RoomHasBeenRead';
    }
}

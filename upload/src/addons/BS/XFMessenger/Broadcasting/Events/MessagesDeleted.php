<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\XFMessenger\Broadcasting\ConversationChannel;
use BS\XFMessenger\Broadcasting\Events\Concerns\ToConversationChannels;
use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;
use XF\Entity\ConversationMaster;

class MessagesDeleted implements Event
{
    use ToConversationChannels;

    public ConversationMaster $conversation;

    public array $criteria;

    public function __construct(ConversationMaster $conversation, array $criteria)
    {
        $this->conversation = $conversation;
        $this->criteria = $criteria;
    }

    public function payload(): array
    {
        return [
            'criteria' => $this->criteria
        ];
    }

    public function broadcastAs(): string
    {
        return 'XFM\MessagesDeleted';
    }
}

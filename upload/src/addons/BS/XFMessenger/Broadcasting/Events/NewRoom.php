<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\XFMessenger\Broadcasting\Events\Concerns\ToConversationChannels;
use BS\XFWebSockets\Broadcasting\UserChannel;
use XF\Entity\ConversationMaster;
use BS\XFWebSockets\Broadcasting\Event;
use XF\Entity\ConversationUser;

class NewRoom implements Event
{
    public ConversationUser $conversationUser;

    public function __construct(ConversationUser $conversationUser)
    {
        $this->conversationUser = $conversationUser;
    }

    public function payload(): array
    {
        return [
            'room' => [
                'tag'   => (string)$this->conversationUser->conversation_id,
            ],
        ];
    }

    public function toChannels(): array
    {
        return [
            new UserChannel($this->conversationUser->owner_user_id)
        ];
    }

    public function broadcastAs(): string
    {
        return 'XFM\NewRoom';
    }
}

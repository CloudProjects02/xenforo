<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\XFMessenger\Broadcasting\Events\Concerns\ToConversationChannels;
use BS\XFWebSockets\Broadcasting\Event;
use XF\Entity\ConversationMaster;
use XF\Entity\User;

class UserTyping implements Event
{
    use ToConversationChannels;

    public User $user;
    public ConversationMaster $conversation;

    public int $delay;

    public function __construct(User $user, ConversationMaster $conversation, int $delay = 4)
    {
        $this->user = $user;
        $this->conversation = $conversation;
        $this->delay = $delay;
    }

    public function payload(): array
    {
        return [
            'user' => [
                'id'   => $this->user->user_id,
                'name' => $this->user->username,
            ],
            'room' => [
                'tag' => (string)$this->conversation->conversation_id,
            ],
            'delay' => $this->delay,
        ];
    }

    public function broadcastAs(): string
    {
        return 'XFM\UserTyping';
    }
}

<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Broadcasting\ChatChannel;
use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use BS\RealTimeChat\Entity\Message;
use BS\XFWebSockets\Broadcasting\UserChannel;

abstract class MessageEvent implements \BS\XFWebSockets\Broadcasting\Event
{
    public Message $message;
    public string $pageUid = '';

    public function __construct(Message $message, string $pageUid = '')
    {
        $this->message = $message;
        $this->pageUid = $pageUid;
    }

    public function toChannels(): array
    {
        if ($this->message->pm_user_id) {
            return [
                new UserChannel($this->message->user_id),
                new UserChannel($this->message->pm_user_id)
            ];
        }

        if ($this->message->Room->isPublic()) {
            return [
                new ChatChannel()
            ];
        }

        return [
            new ChatRoomChannel($this->message->room_tag)
        ];
    }

    public function payload(): array
    {
        return [
            'message'  => $this->message->toBroadcast(),
            'page_uid' => $this->pageUid
        ];
    }

    abstract protected function _broadcastAs(): string;

    public function broadcastAs(): string
    {
        return $this->_broadcastAs();
    }
}

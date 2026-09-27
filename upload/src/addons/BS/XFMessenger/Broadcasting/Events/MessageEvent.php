<?php

namespace BS\XFMessenger\Broadcasting\Events;

use BS\RealTimeChat\Contracts\BroadcastibleMessage;
use BS\XFMessenger\Broadcasting\Events\Concerns\ToConversationChannels;
use XF\Entity\ConversationMaster;

abstract class MessageEvent implements \BS\XFWebSockets\Broadcasting\Event
{
    use ToConversationChannels;

    public ConversationMaster $conversation;

    public BroadcastibleMessage $message;

    public string $pageUid;

    public function __construct(BroadcastibleMessage $message, string $pageUid = '')
    {
        $this->conversation = $message->Conversation;
        $this->message = $message;
        $this->pageUid = $pageUid;
    }

    public function payload(): array
    {
        return [
            'message'  => $this->message->toBroadcast(),
            'page_uid' => $this->pageUid,
        ];
    }

    abstract protected function _broadcastAs(): string;

    public function broadcastAs(): string
    {
        return $this->_broadcastAs();
    }
}

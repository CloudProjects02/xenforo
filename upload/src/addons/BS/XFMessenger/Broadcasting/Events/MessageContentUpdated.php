<?php

namespace BS\XFMessenger\Broadcasting\Events;

class MessageContentUpdated extends MessageEvent
{
    protected function _broadcastAs(): string
    {
        return 'XFM\MessageContentUpdated';
    }
}
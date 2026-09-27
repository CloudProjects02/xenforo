<?php

namespace BS\XFMessenger\Broadcasting\Events;

class NewMessage extends MessageEvent
{
    protected function _broadcastAs(): string
    {
        return 'XFM\NewMessage';
    }
}
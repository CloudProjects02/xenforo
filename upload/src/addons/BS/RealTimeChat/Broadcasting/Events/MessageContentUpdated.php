<?php

namespace BS\RealTimeChat\Broadcasting\Events;

class MessageContentUpdated extends MessageEvent
{
    protected function _broadcastAs(): string
    {
        return 'RTC\MessageContentUpdated';
    }
}
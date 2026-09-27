<?php

namespace BS\RealTimeChat\Broadcasting\Events;

class NewMessage extends MessageEvent
{
    protected function _broadcastAs(): string
    {
        return 'RTC\NewMessage';
    }
}
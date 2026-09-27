<?php

namespace BS\RealTimeChat\Contracts;

interface BroadcastibleMessage
{
    public function toBroadcast(): array;
}

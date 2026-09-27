<?php

namespace BS\XFWebSockets\Broadcasting;

interface Event
{
    public function toChannels(): array;

    public function payload(): array;
}
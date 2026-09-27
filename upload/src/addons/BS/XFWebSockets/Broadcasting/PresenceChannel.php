<?php

namespace BS\XFWebSockets\Broadcasting;

class PresenceChannel extends Channel
{
    public function __construct(mixed $name)
    {
        parent::__construct($name);
        $this->name = 'presence-'.$this->name;
    }
}

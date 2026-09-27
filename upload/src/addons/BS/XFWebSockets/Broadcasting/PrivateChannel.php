<?php

namespace BS\XFWebSockets\Broadcasting;

class PrivateChannel extends Channel
{
    public function __construct(mixed $name)
    {
        parent::__construct($name);
        $this->name = 'private-'.$this->name;
    }
}

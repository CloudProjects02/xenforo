<?php

namespace BS\XFWebSockets\Broadcasting;

use XF\Entity\User;

class ForumChannel extends PrivateChannel
{
    public const NAME_PATTERN = 'Forum';

    public function join(User $visitor): bool
    {
        return $visitor->hasPermission('websockets', 'use');
    }
}

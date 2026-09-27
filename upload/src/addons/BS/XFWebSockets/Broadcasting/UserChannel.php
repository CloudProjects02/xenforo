<?php

namespace BS\XFWebSockets\Broadcasting;

use XF\Entity\User;

class UserChannel extends PrivateChannel
{
    public const NAME_PATTERN = 'User.{id}';

    public function join(User $visitor, int $id): bool
    {
        return $visitor->user_id === $id;
    }
}

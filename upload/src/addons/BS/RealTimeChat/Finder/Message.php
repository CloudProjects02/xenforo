<?php

namespace BS\RealTimeChat\Finder;

use BS\RealTimeChat\Entity\Room;
use XF\Mvc\Entity\Finder;

class Message extends Finder
{
    public function skipIgnored(\XF\Entity\User $user = null)
    {
        if (! $user) {
            $user = \XF::visitor();
        }

        if (! $user->user_id) {
            return $this;
        }

        if ($user->Profile && $user->Profile->ignored) {
            $this->where('user_id', '<>', array_keys($user->Profile->ignored));
        }

        return $this;
    }

    public function skipPm(\XF\Entity\User $user = null)
    {
        if (! $user) {
            $user = \XF::visitor();
        }

        if (! $user->user_id) {
            $this->where(['pm_user_id', '=', 0]);
            return $this;
        }

        $this->whereOr([
            ['pm_user_id', '=', 0],
            ['pm_user_id', '=', $user->user_id],
            ['user_id', '=', $user->user_id]
        ]);

        return $this;
    }

    /**
     * @param Room|string $tag
     * @return $this
     */
    public function inRoom($tag)
    {
        if (! $tag) {
            return $this;
        }

        if ($tag instanceof Room) {
            $tag = $tag->tag;
        }

        $this->where('room_tag', $tag);
        return $this;
    }
}
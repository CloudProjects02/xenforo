<?php

namespace BS\RealTimeChat\Finder;

use XF\Mvc\Entity\Finder;

class Room extends Finder
{
    public function onlyPinned(?\XF\Entity\User $forUser = null)
    {
        if ($forUser) {
            $this->whereOr([
                ['pinned', '=', true],
                ['Members|'.$forUser->user_id.'.room_pinned', '=', true]
            ]);
        } else {
            $this->where('pinned', true);
        }

        return $this;
    }

    public function skipPinned(?\XF\Entity\User $forUser = null, array $exceptTags = [])
    {
        if ($exceptTags) {
            $this->whereOr([
                ['pinned', false],
                ['tag', '=', $exceptTags]
            ]);
        } else {
            $this->where('pinned', false);
        }

        if (! $forUser) {
            return $this;
        }

        if ($exceptTags) {
            $this->whereOr([
                ['Members|'.$forUser->user_id.'.room_pinned', false],
                ['tag', '=', $exceptTags]
            ]);
        } else {
            $this->where('Members|'.$forUser->user_id.'.room_pinned', false);
        }

        return $this;
    }
}

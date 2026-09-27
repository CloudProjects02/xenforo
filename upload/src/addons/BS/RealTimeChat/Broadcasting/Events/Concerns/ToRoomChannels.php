<?php

namespace BS\RealTimeChat\Broadcasting\Events\Concerns;

use BS\RealTimeChat\Broadcasting\ChatChannel;
use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use BS\XFWebSockets\Broadcasting\UserChannel;

trait ToRoomChannels
{
    public function toChannels(): array
    {
        if ($this->room->isPublic()) {
            return [
                new ChatChannel()
            ];
        }

        if ($this->room->isMemberType()) {
            $memberIds = $this->room->members;

            if (empty($memberIds)) {
                return [];
            }

            $excludedMemberIds = $this->excludeRecipientIds ?? [];

            $memberIds = array_diff($memberIds, $excludedMemberIds);

            return array_map(static function ($memberId) {
                return new UserChannel($memberId);
            }, $memberIds);
        }

        return [
            new ChatRoomChannel($this->room->tag),
        ];
    }
}

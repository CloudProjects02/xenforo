<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Broadcasting\ChatChannel;
use BS\RealTimeChat\Broadcasting\ChatRoomChannel;
use BS\RealTimeChat\Entity\Room;
use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\UserChannel;
use XF\Entity\User;

class RoomsDeleted implements Event
{
    public ?User $user = null;
    public ?Room $room = null;
    public array $criteria;

    public function __construct(array $to, array $criteria)
    {
        if (isset($to['user'])) {
            $this->user = $to['user'];
        } else if (isset($to['room'])) {
            $this->room = $to['room'];
        } else {
            throw new \InvalidArgumentException('You must specify a user or a room.');
        }

        $this->criteria = $criteria;
    }

    public function toChannels(): array
    {
        if ($this->room) {
            $channel = $this->room->isPublic()
                ? new ChatChannel()
                : new ChatRoomChannel($this->room->route_tag);
            return [
                $channel
            ];
        }

        return [
            new UserChannel($this->user->user_id)
        ];
    }

    public function payload(): array
    {
        return [
            'criteria' => $this->criteria
        ];
    }

    public function broadcastAs(): string
    {
        return 'RTC\RoomsDeleted';
    }
}

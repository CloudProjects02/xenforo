<?php

namespace BS\RealTimeChat\Broadcasting\Events;

use BS\RealTimeChat\Broadcasting\Events\Concerns\ToRoomChannels;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Entity\RoomMember;
use BS\XFWebSockets\Broadcasting\Event;

class RoomHasBeenRead implements Event
{
    use ToRoomChannels;

    public Room $room;

    public RoomMember $member;

    public array $excludeRecipientIds = [];

    public function __construct(RoomMember $member)
    {
        $this->room = $member->Room;
        $this->member = $member;
        $this->excludeRecipientIds = [$member->user_id];
    }

    public function payload(): array
    {
        return [
            'room' => [
                'tag' => $this->room->tag,
            ],
            'user' => [
                'id'        => $this->member->user_id,
                'read_date' => $this->member->last_view_date,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'RTC\RoomHasBeenRead';
    }
}

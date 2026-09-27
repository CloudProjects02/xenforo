<?php

namespace BS\RealTimeChat\Broadcasting;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Entity\RoomMember;
use BS\XFWebSockets\Broadcast as BaseBroadcast;
use XF\Entity\User;

class Broadcast
{
    public static function newMessage(Message $message, string $pageUid = '')
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:NewMessage',
            $message,
            $pageUid
        );
    }

    public static function messageContentUpdated(Message $message)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:MessageContentUpdated',
            $message
        );
    }

    public static function messagesDeleted(string $roomTag, array $criteria)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:MessagesDeleted',
            $roomTag,
            $criteria
        );
    }

    public static function userTyping(User $user, string $roomTag, int $delay = 4)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:UserTyping',
            $user,
            $roomTag,
            $delay
        );
    }

    public static function wasBanned(User $user, string $roomTag)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:WasBanned',
            $user,
            $roomTag
        );
    }

    public static function newRoom(User $user, Room $room)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:NewRoom',
            $user,
            $room
        );
    }

    public static function roomHasBeenRead(RoomMember $member)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:RoomHasBeenRead',
            $member
        );
    }

    public static function roomsDeleted(array $to, array $criteria)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:RoomsDeleted',
            $to,
            $criteria
        );
    }

    public static function roomUpdated(Room $room)
    {
        BaseBroadcast::event(
            'BS\RealTimeChat:RoomUpdated',
            $room
        );
    }
}

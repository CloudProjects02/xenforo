<?php

namespace BS\XFMessenger\Broadcasting;

use BS\XFWebSockets\Broadcast as BaseBroadcast;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationRecipient;
use XF\Entity\ConversationUser;
use XF\Entity\User;

class Broadcast
{
    public static function newMessage(ConversationMessage $message, string $pageUid = '')
    {
        BaseBroadcast::event(
            'BS\XFMessenger:NewMessage',
            $message,
            $pageUid
        );
    }

    public static function messageContentUpdated(ConversationMessage $message)
    {
        BaseBroadcast::event(
            'BS\XFMessenger:MessageContentUpdated',
            $message
        );
    }

    public static function messagesDeleted(ConversationMaster $conversation, array $criteria)
    {
        BaseBroadcast::event(
            'BS\XFMessenger:MessagesDeleted',
            $conversation,
            $criteria
        );
    }

    public static function userTyping(User $user, ConversationMaster $conversation, int $delay = 4)
    {
        BaseBroadcast::event(
            'BS\XFMessenger:UserTyping',
            $user,
            $conversation,
            $delay
        );
    }

    public static function newRoom(ConversationUser $convUser)
    {
        BaseBroadcast::event(
            'BS\XFMessenger:NewRoom',
            $convUser
        );
    }

    public static function roomHasBeenRead(ConversationRecipient $recipient)
    {
        BaseBroadcast::event(
            'BS\XFMessenger:RoomHasBeenRead',
            $recipient
        );
    }

    public static function roomsDeleted(array $criteria, array $recipientIds)
    {
        BaseBroadcast::event(
            'BS\XFMessenger:RoomsDeleted',
            $criteria,
            $recipientIds
        );
    }

    public static function roomUpdated(ConversationMaster $conversation, array $recipientIds = [])
    {
        BaseBroadcast::event(
            'BS\XFMessenger:RoomUpdated',
            $conversation,
            $recipientIds
        );
    }
}

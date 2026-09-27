<?php

namespace BS\XFMessenger;

use BS\XFMessenger\Broadcasting\Broadcast;
use BS\XFMessenger\Broadcasting\ConversationChannel;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationRecipient;

class Listener
{
    public static function broadcastChannels(array &$channels)
    {
        $channels['Conversation.{id}'] = ConversationChannel::class;
    }

    public static function entityPostSaveConversationRecipient(ConversationRecipient $recipient)
    {
        if ($recipient->isChanged('last_read_date')) {
            Broadcast::roomHasBeenRead($recipient);

            \XF::repository('BS\XFMessenger:Message')
                ->markReadMessagesBeforeDate(
                    $recipient->Conversation,
                    $recipient->User,
                    $recipient->last_read_date
                );

            \XF::repository('BS\XFMessenger:Conversation')
                ->rebuildUnreadCount($recipient->Conversation, $recipient->user_id);
        }

        if ($recipient->isStateChanged('recipient_state', 'active') === 'leave') {
            Broadcast::roomsDeleted(
                ['tags' => (array)$recipient->conversation_id],
                [$recipient->user_id]
            );
        }
    }
}

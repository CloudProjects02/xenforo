<?php

namespace BS\XFMessenger\Broadcasting;

use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Utils\RoomTag;
use BS\XFWebSockets\Broadcasting\PresenceChannel;
use XF\Entity\User;

class ConversationChannel extends PresenceChannel
{
    public function __construct($id)
    {
        parent::__construct('Conversation.' . $id);
    }

    public function join(User $visitor, $id)
    {
        if (! $id) {
            return false;
        }

        $conversation = $this->assertViewableUserConversation(
            $visitor,
            $id
        );

        if (! $conversation) {
            return false;
        }

        return true;
    }

    /**
     * @param  \XF\Entity\User  $visitor
     * @param $conversationId
     * @param  array  $extraWith
     *
     * @return \XF\Entity\ConversationUser
     */
    protected function assertViewableUserConversation(
        User $visitor,
        $conversationId,
        array $extraWith = []
    ) {
        /** @var \XF\Finder\ConversationUser $finder */
        $finder = \XF::finder('XF:ConversationUser');
        $finder->forUser($visitor, false);
        $finder->where('conversation_id', $conversationId);
        $finder->with($extraWith);

        /** @var \XF\Entity\ConversationUser $conversation */
        $conversation = $finder->fetchOne();
        if (! $conversation || ! $conversation->Master) {
            return null;
        }

        return $conversation;
    }
}
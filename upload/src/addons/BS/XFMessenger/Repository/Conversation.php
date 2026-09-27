<?php

namespace BS\XFMessenger\Repository;

use BS\XFMessenger\Job\Conversation\UpdateMembersUnreadCount;
use XF\Entity\ConversationMaster;
use XF\Mvc\Entity\Repository;

class Conversation extends Repository
{
    public function rebuildUnreadCount(ConversationMaster $conversation, ?int $forUserId = null)
    {
        UpdateMembersUnreadCount::create($conversation->conversation_id, $forUserId);
    }
}

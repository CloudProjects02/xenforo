<?php

namespace BS\XFMessenger\XF\Entity;

use BS\RealTimeChat\Entity\Concerns\UpdateLockable;
use BS\XFMessenger\Job\ConversationRecipient\TouchLastRead;

class ConversationRecipient extends XFCP_ConversationRecipient
{
    use UpdateLockable;

    public function touchLastRead(?int $date = null): void
    {
        if ($this->isDeleted()) {
            return;
        }

        TouchLastRead::create($this->conversation_id, $this->user_id, $date);
    }
}

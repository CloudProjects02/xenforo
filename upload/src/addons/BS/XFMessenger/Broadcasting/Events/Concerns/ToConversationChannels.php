<?php

namespace BS\XFMessenger\Broadcasting\Events\Concerns;

use BS\XFWebSockets\Broadcasting\UserChannel;

trait ToConversationChannels
{
    public function toChannels(): array
    {
        $recipientIds = array_keys($this->conversation->recipients);
        $recipientIds[] = $this->conversation->user_id;
        $recipientIds = array_unique($recipientIds);

        if (! empty($this->recipientIds)) {
            $recipientIds = array_intersect($recipientIds, $this->recipientIds);
        }

        if (! empty($this->excludeRecipientIds)) {
            $recipientIds = array_diff($recipientIds, $this->excludeRecipientIds);
        }

        $channels = [];

        foreach ($recipientIds as $recipientId) {
            $channels[] = new UserChannel($recipientId);
        }

        return $channels;
    }
}

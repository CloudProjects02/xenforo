<?php

namespace BS\RealTimeChat\Job\Message;

use XF\Job\AbstractJob;

class SendNotifications extends AbstractJob
{
    public function run($maxRunTime)
    {
        $messageId = $this->data['message_id'];
        /** @var \BS\RealTimeChat\Entity\Message $message */
        $message = $this->app->em()->find('BS\RealTimeChat:Message', $messageId, ['Room']);
        if (!$message) {
            return $this->complete();
        }

        $mentionedUserIds = $this->data['mentioned_user_ids'] ?? [];
        $quotedUserIds = $this->data['quoted_user_ids'] ?? [];

        /** @var \BS\RealTimeChat\Service\Message\Notifier $notifier */
        $notifier = $this->app->service('BS\RealTimeChat:Message\Notifier', $message);

        if (! empty($mentionedUserIds)) {
            $notifier->setMentionedUserIds($mentionedUserIds);
        }

        if (! empty($quotedUserIds)) {
            $notifier->setQuotedUserIds($quotedUserIds);
        }

        if ($message->pm_user_id) {
            $notifier->setPmUserId($message->pm_user_id);
        }

        $notifier->notifyAndEnqueue($maxRunTime);

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Send notifications for chat message';
    }

    public function canTriggerByChoice()
    {
        return false;
    }

    public function canCancel()
    {
        return false;
    }
}

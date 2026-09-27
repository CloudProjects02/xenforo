<?php

namespace BS\XFMessenger\Job\Conversation;

use XF\Job\AbstractJob;

class NotifyReply extends AbstractJob
{
    public function run($maxRunTime)
    {
        $messageId = $this->data['message_id'];
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $this->app->em()->find('XF:ConversationMessage', $messageId, ['Conversation']);
        if (!$message) {
            return $this->complete();
        }

        /** @var \XF\Service\Conversation\Notifier $notifier */
        $notifier =\XF::service('XF:Conversation\Notifier', $message->Conversation);
        $notifier->notifyReply($message);

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Send notifications for conversation';
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
<?php

namespace BS\XFMessenger\XF\Service\Conversation;

class ReplierService extends XFCP_ReplierService
{
    public function sendNotifications()
    {
        // run notifications send in job, so we don't have to wait for them to be sent
        // and user receive response much faster

        $key = substr(
            md5('conv-reply-notifications:' . $this->conversation->conversation_id),
            0,
            25
        );

        $this->app->jobManager()->enqueueUnique(
            $key,
            'BS\XFMessenger:Conversation\NotifyReply',
            [
                'message_id' => $this->message->message_id,
            ]
        );
    }
}

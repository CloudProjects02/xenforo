<?php

namespace BS\RealTimeChat\Pub\Controller\Concerns;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;
use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\XFWebSockets\Request;

trait Messages
{
    protected function afterPost(Creator $messageCreator)
    {
        $messageCreator->sendNotifications();
    }

    /**
     * @param $id
     * @param  null  $with
     * @return Message
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertMessageExists($id, $with = null)
    {
        return $this->assertRecordExists(
            'BS\RealTimeChat:Message',
            (int)$id,
            $with,
            'real_time_chat_requested_message_not_found'
        );
    }
}

<?php

namespace BS\XFMessenger\XF\Service\Conversation;

use BS\XFMessenger\Broadcasting\Broadcast;
use BS\XFWebSockets\Request;

class MessageManagerService extends XFCP_MessageManagerService
{
    public function afterInsert()
    {
        parent::afterInsert();
        Broadcast::newMessage($this->conversationMessage, Request::getPageUid());
    }
}

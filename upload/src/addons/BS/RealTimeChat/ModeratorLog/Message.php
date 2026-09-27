<?php

namespace BS\RealTimeChat\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Message extends AbstractHandler
{
    public function isLoggable(Entity $content, $action, \XF\Entity\User $actor)
    {
        if ($content->pm_user_id) {
            return false;
        }

        switch ($action) {
            case 'edit':
            case 'attachment_deleted':
                if ($actor->user_id === $content->user_id) {
                    return false;
                }
        }

        return parent::isLoggable($content, $action, $actor);
    }

    protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
    {
        if ($field === 'message') {
            return 'edit';
        }

        return false;
    }

    protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
    {
        /** @var \BS\RealTimeChat\Entity\Message $content */
        $log->content_user_id = $content->user_id;
        $log->content_username = $content->User->username ?? '';
        $log->content_title = $content->Room->tag ?: '';
        $log->content_url = \XF::app()->router('public')->buildLink('nopath:chat/messages/to', $content);
        $log->discussion_content_type = 'rtc_room';
        $log->discussion_content_id = $content->room_id;
    }

    public function getContentTitle(ModeratorLog $log)
    {
        return \XF::phrase('rtc_message_in_room_x', [
            'title' => $log->content_title_
        ]);
    }
}

<?php

namespace BS\RealTimeChat\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Room extends AbstractHandler
{
    public function isLoggable(Entity $content, $action, \XF\Entity\User $actor)
    {
        switch ($action) {
            case 'edit':
            case 'wallpaper_changed':
                if ($actor->user_id === $content->user_id) {
                    return false;
                }
        }

        return parent::isLoggable($content, $action, $actor);
    }

    protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
    {
        if (in_array($field, ['wallpaper_date', 'wallpaper_options'])) {
            return 'wallpaper_changed';
        }

        if (in_array($field, ['tag', 'description'])) {
            return 'edit';
        }

        return false;
    }

    protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
    {
        /** @var \BS\RealTimeChat\Entity\Room $content */
        $log->content_user_id = (int)$content->user_id;
        $log->content_username = $content->User->username ?? '';
        $log->content_title = $content->tag ?: '';
        $log->content_url = \XF::app()->router('public')->buildLink('nopath:chat', $content);
        $log->discussion_content_type = 'rtc_room';
        $log->discussion_content_id = $content->room_id;
    }

    public function getContentTitle(ModeratorLog $log)
    {
        return \XF::phrase('rtc_room_x', [
            'title' => $log->content_title_
        ]);
    }
}

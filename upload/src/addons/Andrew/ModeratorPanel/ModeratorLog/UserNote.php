<?php

namespace Andrew\ModeratorPanel\ModeratorLog;
use XF\Entity\ModeratorLog;
use XF\Mvc\Entity\Entity;


class UserNote extends \XF\ModeratorLog\AbstractHandler
{

    public function isLoggable(Entity $content, $action, \XF\Entity\User $actor)
    {
        switch ($action)
        {
            case 'created':
                if ($actor->user_id == $content->user_id)
                {
                    return false;
                }
        }

        return parent::isLoggable($content, $action, $actor);
    }

    protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
    {
        return false;
    }

    protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
    {

        if(is_object($content->NoteUser)) {
            $log->content_user_id = $content->NoteUser->user_id;
            $log->content_username = $content->NoteUser->username;
            $log->content_title = $content->NoteUser->username;
        }
            $log->content_url = \XF::app()->router('public')->buildLink('nopath:moderatorpanel/user/#user_notes', $content->NoteUser);
            $log->discussion_content_type = 'user_note';
            $log->discussion_content_id = $content->note_id;

    }
}

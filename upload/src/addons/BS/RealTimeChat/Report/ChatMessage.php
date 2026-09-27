<?php

namespace BS\RealTimeChat\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;

class ChatMessage extends AbstractHandler
{
    protected function canActionContent(Report $report)
    {
        return \XF::visitor()->hasPermission('general', 'warn');
    }

    public function setupReportEntityContent(Report $report, Entity $content)
    {
        $report->content_user_id = $content->user_id;
        $report->content_info = [
            'room_tag'   => $content->room_tag,
            'message_id' => $content->message_id,
            'message'    => $content->message,
            'user_id'    => $content->user_id,
            'username'   => $content->User->username
        ];
    }

    public function getContentTitle(Report $report)
    {
        if (isset($report->content_info['room_tag'])) {
            return \XF::phrase('rtc_chat_message_in_the_room_x', [
                'room' => $report->content_info['room_tag']
            ]);
        }

        return \XF::phrase('chat_message');
    }

    public function getContentMessage(Report $report)
    {
        return $report->content_info['message'];
    }

    public function getContentLink(Report $report)
    {
        $data = [
            'tag'   => $report->content_info['room_tag'] ?? null,
            'message_id' => $report->content_info['message_id'] ?? null
        ];

        if (empty($data['tag']) || empty($data['message_id'])) {
            return null;
        }

        return \XF::app()->router('public')
            ->buildLink('canonical:chat/messages/to', $data);
    }

    public function getEntityWith()
    {
        return ['User'];
    }
}
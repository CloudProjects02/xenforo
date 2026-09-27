<?php

namespace BS\RealTimeChat\Widget;

use BS\RealTimeChat\Repository\Message;
use XF\Widget\AbstractWidget;

class Chat extends AbstractWidget
{
    public function render()
    {
        if (\XF::options()->rtcDisable) {
            return null;
        }

        $visitor = \XF::visitor();
        if (! $visitor->canViewChat()) {
            return null;
        }

        $latestMessageDate = 0;
        $attachmentData = $this->getReplyAttachmentData();

        $defaultRoomTag = $this->options['defaultTag'] ?? null;
        $compact = $this->options['compact'] ?? false;

        $this->app->container()->set('hasRtcWidget', true);

        return $this->renderer(
            'widget_real_time_chat',
            compact('latestMessageDate', 'attachmentData', 'defaultRoomTag', 'compact')
        );
    }

    protected function getReplyAttachmentData()
    {
        if (! \XF::visitor()->hasChatPermission('uploadAttachment')) {
            return null;
        }

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        return $attachmentRepo->getEditorData('chat_message');
    }

    /** @return Message */
    protected function getMessageRepo()
    {
        return $this->repository('BS\RealTimeChat:Message');
    }
}

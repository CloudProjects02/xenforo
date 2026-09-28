<?php

namespace AL\Core\Service;

use XF\Entity\Attachment;
use XF\Service\AbstractService;

class SingleAttachment extends AbstractService
{
    public function assertSingleAttachment(Attachment $lastAttachment)
    {
        if (!$lastAttachment->content_id || !$lastAttachment->content_type)
        {
            return;
        }

        // Fetch all attachments other than this one
        /** @var Attachment[] $attachments */
        $attachments = \XF::finder('XF:Attachment')
            ->where('content_id', $lastAttachment->content_id)
            ->where('content_type', $lastAttachment->content_type)
            // single attachment system is always meant to replace the older attachment
            // this would cause some issue in case of multiple attachments
            ->where('attachment_id', '<', $lastAttachment->attachment_id)
            ->fetch();

        foreach ($attachments as $attachment)
        {
            $attachment->delete();
        }
    }
}
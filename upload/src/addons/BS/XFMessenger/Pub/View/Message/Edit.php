<?php

namespace BS\XFMessenger\Pub\View\Message;

use XF\Entity\Attachment;
use XF\Mvc\View;

class Edit extends View
{
    public function renderJson()
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $this->params['message'];

        $templater = $this->renderer->getTemplater();

        $messageHtml = $templater->func('bb_code', [$message->message, 'conversation_message', $message]);

        $attachmentData = null;
        if ($message->Conversation->canUploadAndManageAttachments()) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = \XF::repository('XF:Attachment');
            $attachmentData = $attachmentRepo->getEditorData('conversation_message', $message);

            if (isset($attachmentData['attachments'])) {
                $attachmentData['attachments'] = array_map(
                    fn(Attachment $attachment) => $attachment->toApiResult()->render(),
                    $attachmentData['attachments']
                );
                $attachmentData['attachments'] = array_values($attachmentData['attachments']);
            }

            $attachmentData['manageUrl'] = \XF::app()->router('public')
                ->buildLink('attachments/upload', null, [
                    'type' => $attachmentData['type'],
                    'context' => $attachmentData['context'],
                    'hash' => $attachmentData['hash'],
                ]);
        }

        return [
            'message' => [
                'id'          => $message->message_id,
                'html'        => $messageHtml,
            ],
            'attachmentData' => $attachmentData,
        ];
    }

}
<?php

namespace BS\RealTimeChat\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class Message extends AbstractHandler
{
    public function canView(Attachment $attachment, Entity $container, &$error = null)
    {
        /** @var \BS\RealTimeChat\Entity\Message $container */
        return $container->canView();
    }

    public function canManageAttachments(array $context, &$error = null)
    {
        /** @var \BS\RealTimeChat\Entity\Message $message */
        $message = $this->getMessageFromContext($context);
        if ($message) {
            return $message->canEdit();
        }

        return \XF::visitor()->hasChatPermission('uploadAttachment');
    }

    public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
    {
        if (!$container) {
            return;
        }

        /** @var \BS\RealTimeChat\Entity\Message $container */
        $container->attach_count--;
        $container->save();

        \XF::app()->logger()->logModeratorAction(
            $this->contentType,
            $container,
            'attachment_deleted',
            [],
            false
        );
    }

    public function getConstraints(array $context)
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = \XF::repository('XF:Attachment');
        return $attachRepo->getDefaultAttachmentConstraints();
    }

    public function getContainerIdFromContext(array $context)
    {
        return isset($context['message_id'])
            ? (int)$context['message_id']
            : null;
    }

    public function getContext(Entity $entity = null, array $extraContext = [])
    {
        $extraContext['message_id'] = $entity->message_id ?? null;
        return $extraContext;
    }

    protected function getMessageFromContext(array $context)
    {
        $em = \XF::em();

        if (empty($context['message_id'])) {
            return null;
        }

        return $em->find('BS\RealTimeChat:Message', $context['message_id']);
    }
}

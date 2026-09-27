<?php

namespace BS\XFMessenger\Pub\Controller;

use BS\RealTimeChat\Pub\Controller\Concerns\RequestLimitations;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Message extends AbstractController
{
    use RequestLimitations;

    public function actionReport(ParameterBag $params)
    {
        $conversationId = $params->conversation_id;
        $messageId = $params->message_id;

        $newParams = new ParameterBag([
            'conversation_id' => $conversationId,
            'message_id' => $messageId
        ]);

        return $this->rerouteController('XF:Conversation', 'messages-report', $newParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $message = $this->assertViewableMessage($params->message_id);

        if (! $message->canEdit()) {
            return $this->noPermission();
        }

        return $this->view(
            'BS\XFMessenger:Message\Edit',
            '',
            compact('message'),
        );
    }

    /**
     * @param  \XF\Entity\ConversationMessage  $conversationMessage
     * @return \XF\Service\Conversation\MessageEditor
     */
    protected function setupMessageEdit(\XF\Entity\ConversationMessage $conversationMessage)
    {
        $message = $this->plugin('XF:Editor')->convertToBbCode(
            $this->filter('message', 'str')
        );

        /** @var \XF\Service\Conversation\MessageEditor $editor */
        $editor = $this->service('XF:Conversation\MessageEditor', $conversationMessage);
        $editor->setMessageContent($message);

        $conversation = $conversationMessage->Conversation;

        if ($conversation->canUploadAndManageAttachments()) {
            $editor->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $editor;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertXhrOnly();
        $this->assertPostOnly();

        $message = $this->assertViewableMessage($params->message_id);
        if (! $message->canEdit($error)) {
            return $this->noPermission($error);
        }

        $editor = $this->setupMessageEdit($message);
        if (! $editor->validate($errors)) {
            return $this->error($errors);
        }
        $editor->save();

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionDelete(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $message = $this->assertViewableMessage($params->message_id);
        if (! $message->canDelete()) {
            return $this->noPermission();
        }

        $message->delete();

        return $this->redirect($this->getDynamicRedirect());
    }

    /**
     * @param $messageId
     * @param array $extraWith
     *
     * @return \XF\Entity\ConversationMessage
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableMessage($messageId, array $extraWith = [])
    {
        $extraWith[] = 'Conversation';

        $visitor = \XF::visitor();
        if ($visitor->user_id) {
            $extraWith[] = 'Conversation.Recipients|'.$visitor->user_id;
            $extraWith[] = 'Conversation.Users|'.$visitor->user_id;
        }

        $extraWith = array_unique($extraWith);

        /** @var \XF\Entity\ConversationMessage $message */
        $message = $this->em()->find('XF:ConversationMessage', $messageId, $extraWith);
        if (! $message) {
            throw $this->exception($this->notFound(\XF::phrase('requested_message_not_found')));
        }
        if (! $message->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $message;
    }
}

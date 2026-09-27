<?php

namespace BS\XFMessenger\XF\Pub\Controller;

use BS\RealTimeChat\Pub\Controller\Concerns\Language;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationUser;
use XF\Mvc\ParameterBag;

class ConversationController extends XFCP_ConversationController
{
    use Language;

    public function actionIndex(ParameterBag $params)
    {
        if ($this->options()->xfmReplaceConversationsWithMessenger) {
            return $this->redirect($this->buildLink(
                'conversations/messenger',
                ['tag' => $params->conversation_id],
                ['filter' => $this->filter('filter', 'array')]
            ));
        }

        $tag = $params->conversation_id;

        if ($tag) {
            try {
                $this->assertViewableUserConversation($tag);
            } catch (\XF\Mvc\Reply\Exception $e) {
                return $this->redirect($this->buildLink('conversations'));
            }
        }

        $latestMessageDate = 0;

        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $this->em()->create('XF:ConversationMaster');

        $attachmentData = null;

        if ($conversation->canUploadAndManageAttachments()) {
            $draft = \XF\Draft::createFromKey('conversation');
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->repository('XF:Attachment');
            $attachmentData = $attachmentRepo->getEditorData(
                'conversation_message',
                null,
                $draft->attachment_hash
            );
        }

        return $this->view(
            'BS\XFMessenger:Conversations',
            'xf_messenger_conversations',
            compact('attachmentData', 'latestMessageDate', 'tag')
        );
    }

    public function actionAdd()
    {
        if ($this->isPost()) {
            $response = parent::actionAdd();

            // remove message from redirect
            if ($response instanceof \XF\Mvc\Reply\Redirect) {
                $response->setMessage('');
            }

            return $response;
        }

        $to = $this->filter('to', 'str');
        $title = $this->filter('title', 'str');
        $message = $this->filter('message', 'str');

        return $this->redirect($this->buildLink('conversations/messenger', null, [
            'c' => compact('to', 'title', 'message') + ['open' => true]
        ]));
    }

    protected function setupConversationCreate()
    {
        $creator = parent::setupConversationCreate();

        $message = $this->plugin('XF:Editor')->convertToBbCode(
            $this->filter('message', 'str')
        );
        $creator->setContent($creator->getConversation()->title, $message);

        return $creator;
    }

    public function actionWallpaper(ParameterBag $params)
    {
        $userConv = $this->assertViewableUserConversation($params->conversation_id, ['Master']);
        $conversation = $userConv->Master;

        if (! ($conversation->canSetWallpaper()
            || $conversation->canSetIndividualWallpaper())
        ) {
            return $this->noPermission();
        }

        if ($this->isPost()) {
            return $this->updateWallpaper($conversation, $userConv);
        }

        return $this->view(
            'BS\XFMessenger:Wallpaper\Set',
            'xf_messenger_conversation_wallpaper_set',
            compact('conversation', 'userConv')
        );
    }

    protected function updateWallpaper(
        ConversationMaster $conversation,
        ConversationUser $userConv
    ) {
        $visitor = \XF::visitor();

        /** @var \BS\XFMessenger\XF\Service\Conversation\Editor $roomEditor */
        $roomEditor = $this->service('XF:Conversation\Editor', $conversation);

        $wallpaper = $this->request->getFile('wallpaper');
        $forRoom = $this->filter('for_room', 'bool');
        $deleteWallpaper = $this->filter('delete_wallpaper', 'bool');
        $resetWallpaper = $this->filter('reset_wallpaper', 'bool');
        $resetRoomWallpaper = $this->filter('reset_room_wallpaper', 'bool');

        if ($resetWallpaper && ! $conversation->canResetWallpaper()) {
            return $this->noPermission();
        }

        if ($resetRoomWallpaper && ! $conversation->canEdit()) {
            return $this->noPermission();
        }

        $options = $this->filter([
            'blurred'     => 'bool',
            'theme_index' => 'int',
        ]);

        $forUser = $forRoom ? null : $visitor;

        if ($deleteWallpaper) {
            $roomEditor->deleteWallpaper($forUser, $options);
        } else if ($resetWallpaper || $resetRoomWallpaper) {
            $roomEditor->resetWallpaper($resetRoomWallpaper ? null : $forUser);
        } else {
            $roomEditor->updateWallpaper(
                $options,
                $wallpaper,
                $forUser,
                $errors
            );

            if (! empty($errors)) {
                return $this->error($errors);
            }
        }

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionMessages(ParameterBag $params)
    {
        $messageId = $params->message_id;
        $convMessage = $this->em()->find('XF:ConversationMessage', $messageId, ['Conversation']);

        return $this->redirect(
            $this->buildLink(
                'conversations/messenger',
                ['tag' => $convMessage->conversation_id],
                [
                    'filter' => [
                        'around_message_id' => $messageId
                    ]
                ]
            )
        );
    }

    public function actionDraft(ParameterBag $params)
    {
        if ($this->isPost()) {
            return parent::actionDraft($params);
        }

        if ($params->conversation_id) {
            $conversation = $this->assertViewableUserConversation($params->conversation_id);

            $draft = $conversation->Master->draft_reply;
        } else {
            $visitor = \XF::visitor();

            if (! $visitor->canStartConversation()) {
                return $this->noPermission();
            }

            $draft = \XF\Draft::createFromKey('conversation');
        }

        if ($this->filter('attachments', 'bool')) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->repository('XF:Attachment');
            $attachmentData = $attachmentRepo->getEditorData(
                'conversation_message',
                null,
                $draft->attachment_hash
            );

            return $this->view(
                'BS\XFMessenger:Draft\Attachments',
                'xf_messenger_draft_attachments',
                compact('attachmentData')
            );
        }

        $draftInfo = $draft->getDraftEntity();

        $response = $this->view();
        $response->setJsonParams([
            'draft' => [
                'message'    => $draftInfo->message ?? '',
                'extra_data' => $draftInfo->extra_data ?? [],
            ]
        ]);
        return $response;
    }

    public function actionMessagesTranslate(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);
        if (! $message->canTranslate()) {
            return $this->noPermission();
        }

        $visitor = \XF::visitor();
        if ($message->hasTranslationForUser($visitor->user_id)) {
            $message->removeTranslation($visitor->user_id);
        } else {
            $message->translate($this->getChatLanguageCode());
        }

        $redirect = $this->redirect($this->getDynamicRedirect());
        $redirect->setMessage('');
        return $redirect;
    }
}

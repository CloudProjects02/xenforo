<?php

namespace BS\XFMessenger\Pub\Controller;

use BS\RealTimeChat\Pub\Controller\Concerns\RequestLimitations;
use BS\XFMessenger\Broadcasting\Broadcast;
use XF\Entity\ConversationMaster;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\Conversation;

class Messenger extends Conversation
{
    use RequestLimitations;

    public function actionIndex(ParameterBag $params)
    {
        $tag = $params->tag;
        if ($tag && ! $this->filter('filter.around_room_tag', 'str')) {
            $filter = $this->filter('filter', 'array-str');
            return $this->redirect(
                $this->buildLink(
                    'conversations/messenger',
                    compact('tag'),
                    [
                        'filter' => [
                            'around_room_tag' => $tag,
                        ] + $filter
                    ]
                )
            );
        }

        if ($tag) {
            try {
                $this->assertViewableUserConversation($tag);
            } catch (\XF\Mvc\Reply\Exception $e) {
                return $this->redirect($this->buildLink('conversations'));
            }
        }

        $createForm = $this->filter([
            'c' => [
                'open'    => 'bool',
                'to'      => 'str',
                'title'   => 'str',
                'message' => 'str'
            ]
        ])['c'];

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
            'BS\XFMessenger:Messenger',
            'xf_messenger',
            compact('tag', 'attachmentData', 'latestMessageDate', 'createForm')
        );
    }

    public function actionRooms(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $visitor = \XF::visitor();

        $filter = $this->filter([
            'filter' => [
                'withListInfo'    => 'bool',
                'tag'             => 'str',
                'from_date'       => 'uint',
                'after_date'      => 'uint',
                'around_room_tag' => 'str'
            ]
        ])['filter'];

        $conversationRepo = $this->getConversationRepo();

        $conversationFinder = $conversationRepo->findUserConversations($visitor);
        $conversationFinder->limit($this->options()->discussionsPerPage);
        $conversationFinder->with([
            'Master.LastMessage',
            'Master.LastMessage.User',
            'Master.Users|' . $visitor->user_id,
        ]);

        $roomRepo = $this->getRoomRepo();

        $roomRepo->filterConversations($conversationFinder, $filter);

        $userConvs = $conversationFinder->fetch();

        $jsonResponse = [];
        $roomRepo->extendListJsonResponse($jsonResponse, $userConvs, $conversationFinder, $filter);

        $response = $this->view(
            'BS\XFMessenger:Rooms',
            'xf_messenger_rooms',
            compact('userConvs', 'filter')
        );
        $response->setJsonParams($jsonResponse);
        return $response;
    }

    public function actionMessageList(ParameterBag $params)
    {
        $this->assertXhrOnly();
        $this->assertNotEmbeddedImageRequest();

        $userConv = $this->assertViewableUserConversation(
            $params->tag,
            ['Master.DraftReplies|' . \XF::visitor()->user_id]
        );
        $conversation = $userConv->Master;

        $filter = $this->filter([
            'filter' => [
                'withListInfo'      => 'bool',
                'from_date'         => 'uint',
                'message_id'        => 'uint',
                'start_message_id'  => 'uint',
                'end_message_id'    => 'uint',
                'around_message_id' => 'uint',
                'page'              => 'uint',
            ]
        ])['filter'];

        $conversationRepo = $this->getConversationRepo();
        $conversationMessageRepo = $this->getConversationMessageRepo();

        if (! empty($filter['withListInfo'])
            && empty($filter['around_message_id'])
            && $userConv->isUnread()
        ) {
            /** @var \XF\Entity\ConversationMessage $firstUnreadMessage */
            $firstUnreadMessage = $conversationMessageRepo->getFirstUnreadMessageInConversation($userConv);
            if ($firstUnreadMessage) {
                $filter['around_message_id'] = $firstUnreadMessage->message_id;
            }
        }

        $messageFinder = $conversationMessageRepo->findMessagesForConversationView($conversation);

        $messageRepo = $this->getMessageRepo();

        $wrappedOrder = $messageRepo->getOrderWrappedFromFilter($filter);
        $messageFinder->resetOrder()->order(...$wrappedOrder['order']);

        $messageRepo->filterMessages($messageFinder, $filter);
        $messages = $messageFinder->fetch();

        $messages = $wrappedOrder['sort']($messages);

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentRepo->addAttachmentsToContent($messages, 'conversation_message');

        /** @var \XF\Repository\Unfurl $unfurlRepo */
        $unfurlRepo = $this->repository('XF:Unfurl');
        $unfurlRepo->addUnfurlsToContent($messages, false);

        $lastRead = $userConv->Recipient ? $userConv->Recipient->last_read_date : 0;

        /** @var \XF\Entity\ConversationMessage $lastMessage */
        $lastMessage = $messages->last();

        $jsonResponse = [];
        $messageRepo->extendListJsonResponse($jsonResponse, $messages, $messageFinder, $filter);

        $viewParams = [
            'userConv' => $userConv,
            'conversation' => $conversation,
            'recipients' => $conversationRepo->findRecipientsForList($conversation)->fetch(),

            'lastRead' => $lastRead,
            'messages' => $messages,
            'lastMessage' => $lastMessage,

            'filter' => $filter,

            'attachmentData' => $this->getReplyAttachmentData($conversation)
        ];

        $response = $this->view(
            'BS\XFMessenger:Messages',
            'xf_messenger_messages',
            $viewParams
        );
        $response->setJsonParams($jsonResponse);
        return $response;
    }

    protected function getReplyAttachmentData(ConversationMaster $conversation, $forceAttachmentHash = null)
    {
        if ($conversation->canUploadAndManageAttachments()) {
            $attachmentHash = $forceAttachmentHash ?? $conversation->draft_reply->attachment_hash;

            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->repository('XF:Attachment');
            return $attachmentRepo->getEditorData('conversation_message', $conversation, $attachmentHash);
        }

        return null;
    }

    protected function setupConversationReply(
        ConversationMaster $conversation,
        \XF\Entity\ConversationUser $userConv
    ) {
        $replier = parent::setupConversationReply($conversation, $userConv);

        $message = $this->plugin('XF:Editor')->convertToBbCode(
            $this->filter('message', 'str')
        );
        $replier->setMessageContent($message);

        return $replier;
    }

    public function actionReply(ParameterBag $params)
    {
        $this->assertXhrOnly();
        $this->assertPostOnly();

        $userConv = $this->assertViewableUserConversation($params->tag);
        $conversation = $userConv->Master;
        if (! $conversation->canReply()) {
            return $this->noPermission();
        }

        if ($messageId = $this->filter('message_id', 'uint')) {
            $params->offsetSet('message_id', $messageId);
            return $this->rerouteController(Message::class, 'save', $params);
        }

        $replier = $this->setupConversationReply($conversation, $userConv);
        if (! $replier->validate($errors)) {
            return $this->error($errors);
        }
        $this->assertNotFlooding('conversation_message');
        $message = $replier->save();

        $this->afterConversationReply($replier);

        $response = $this->redirect($this->getDynamicRedirect());
        $response->setJsonParams([
            'insertedMessage' => [
                'id'       => $message->message_id ?? 0,
                'date'     => $message->message_date ?? 0,
                'room_tag' => (string)$conversation->conversation_id,
            ]
        ]);
        return $response;
    }

    protected function getConversationFilterInput()
    {
        return $this->filter([
            'filter' => [
                'starter' => 'str',
                'receiver' => 'str',
                'starred' => 'bool',
                'unread' => 'bool'
            ]
        ])['filter'];
    }

    public function actionSearch()
    {
        $this->assertXhrOnly();

        $type = $this->filter('type', 'str');
        $search = $this->filter('q', 'str');

        switch ($type) {
            case 'conversations':
                $limit = mb_strlen($search) > 2 ? 50 : 10;
                $filter = $this->getConversationFilterInput();
                $userConvs = $this->getRoomRepo()->findUserConversations(
                    \XF::visitor(),
                    $search,
                    $filter
                )->limit($limit)->fetch();

                return $this->view(
                    'BS\XFMessenger:Search\Conversations',
                    'xf_messenger_search_conversations',
                    compact('userConvs')
                );

            case 'attachments':
                $attachments = $this->getAttachmentRepo()->findUserConversationAttachments(
                    \XF::visitor(),
                    $search
                )->limit(50)->fetch();

                return $this->view(
                    'BS\XFMessenger:Search\Attachments',
                    'xf_messenger_search_attachments',
                    compact('attachments')
                );

            default:
                return $this->error(\XF::phrase('requested_page_not_found'));
        }
    }

    public function actionTyping(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $userConv = $this->assertViewableUserConversation($params->tag, ['Master']);
        if (! $userConv->Master->canReply()) {
            return $this->noPermission();
        }

        Broadcast::userTyping(\XF::visitor(), $userConv->Master);

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionMarkSeen(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $userConv = $this->assertViewableUserConversation($params->tag, ['Master']);
        $ids = $this->filter('ids', 'array-uint');
        if (empty($ids)) {
            return $this->noPermission();
        }

        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = $this->repository('XF:UserAlert');
        $userAlertRepo->markUserAlertsReadForContent('conversation_message', $ids);

        $maxMessageId = max($ids);
        $lastMessage = $this->em()->find('XF:ConversationMessage', $maxMessageId);

        $newRead = $lastMessage->message_date ?? null;

        $this->getConversationRepo()->markUserConversationRead($userConv, $newRead);

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionMenu(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $userConv = $this->assertViewableUserConversation($params->tag, ['Master']);
        $conversation = $userConv->Master;

        return $this->view(
            'BS\XFMessenger:Conversation\Menu',
            'xf_messenger_conversation_menu',
            compact('userConv', 'conversation')
        );
    }

    public function actionNotices(ParameterBag $params)
    {
        $this->assertXhrOnly();

        if (! ($startMessageId = $this->filter('start_message_id', 'uint'))) {
            return $this->error(\XF::phrase('requested_page_not_found'));
        }

        $userConv = $this->assertViewableUserConversation($params->tag, ['Master']);
        $conversation = $userConv->Master;

        $messageList = $this->getConversationMessageRepo()->findMessagesForConversationView($conversation)
            ->resetOrder()
            ->order('message_date', 'DESC');

        $this->getMessageRepo()->filterMessages($messageList, [
            'after_message_id' => $startMessageId
        ]);

        $messages = $messageList->fetch()->reverse();

        if ($messages->count() === 0) {
            return $this->view();
        }

        return $this->view(
            'BS\XFMessenger:Conversation\Notices',
            'xf_messenger_conversation_notices',
            compact('userConv', 'conversation', 'messages')
        );
    }

    public function actionRecipients(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $userConv = $this->assertViewableUserConversation($params->tag, ['Master']);
        $conversation = $userConv->Master;

        return $this->view(
            'BS\XFMessenger:Conversation\Recipients',
            'xf_messenger_conversation_recipients',
            compact('userConv', 'conversation')
        );
    }

    public function actionPopup()
    {
        $this->assertNotEmbeddedImageRequest();

        $createForm = $this->filter([
            'c' => [
                'open'    => 'bool',
                'to'      => 'str',
                'title'   => 'str',
                'message' => 'str'
            ]
        ])['c'];

        $tag = $this->filter('tag', 'str');

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
            'BS\XFMessenger:Popup',
            'xf_messenger_popup',
            compact('attachmentData', 'latestMessageDate', 'tag', 'createForm')
        );
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\XFMessenger\Repository\Attachment
     */
    protected function getAttachmentRepo()
    {
        return $this->repository('BS\XFMessenger:Attachment');
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\XFMessenger\Repository\Room
     */
    protected function getRoomRepo()
    {
        return $this->repository('BS\XFMessenger:Room');
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\XFMessenger\Repository\Message
     */
    protected function getMessageRepo()
    {
        return $this->repository('BS\XFMessenger:Message');
    }
}

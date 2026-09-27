<?php

namespace BS\RealTimeChat\Pub\Controller;

use BS\RealTimeChat\Concerns\Repos;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Service\Message\Creator;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\Controller\AbstractController;
use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Pub\Controller\Message as MessageController;

class Chat extends AbstractController
{
    use Concerns\Messages;
    use Concerns\Rooms;
    use Concerns\RequestLimitations;
    use Repos;

    private const SKIP_ROOM_ACCESS_CHECK_ACTIONS = [
        'CreateRoom'
    ];

    protected function preDispatchController($action, ParameterBag $params)
    {
        if ($this->options()->rtcDisable) {
            throw $this->exception($this->notFound());
        }

        if (in_array($action, self::SKIP_ROOM_ACCESS_CHECK_ACTIONS, true)) {
            return;
        }

        if ($params->tag) {
            $params->tag = RoomTag::normalize($params->tag);
            $this->assertAccessibleChatRoom($params->tag);
        }
    }

    public function actionIndex(ParameterBag $params)
    {
        if (! \XF::visitor()->canViewChat()) {
            return $this->noPermission();
        }

        $tag = $params->tag;
        if ($tag && ! $this->filter('filter.around_room_tag', 'str')) {
            $filter = $this->filter('filter', 'array-str');
            return $this->redirect(
                $this->buildLink(
                    'chat',
                    compact('tag'),
                    [
                        'filter' => [
                                'around_room_tag' => $tag,
                        ] + $filter
                    ]
                )
            );
        }

        $latestMessageDate = 0;
        $attachmentData = $this->getReplyAttachmentData();

        return $this->view(
            'BS\RealTimeChat:Window',
            'real_time_chat',
            compact('latestMessageDate', 'tag', 'attachmentData')
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

    public function actionMessageList(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $room = $this->assertAccessibleChatRoom($params->tag);

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

        $jsonResponse = [];

        $messages = $this->getMessageRepo()->getMessagesForList($room, (array)$filter, $jsonResponse);

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentRepo->addAttachmentsToContent($messages, 'chat_message');

        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = $this->repository('XF:UserAlert');
        $userAlertRepo->markUserAlertsReadForContent(
            'chat_message',
            $messages->keys()
        );

        $response = $this->view(
            'BS\RealTimeChat:Messages',
            'real_time_chat_messages',
            compact('room', 'messages', 'filter')
        );
        $response->setJsonParams($jsonResponse);
        return $response;
    }

    public function actionMarkSeen(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $room = $this->assertAccessibleChatRoom($params->tag);
        $ids = $this->filter('ids', 'array-uint');
        if (empty($ids)) {
            return $this->noPermission();
        }

        if (! \XF::visitor()->user_id) {
            return $this->redirect($this->getDynamicRedirect());
        }

        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = $this->repository('XF:UserAlert');
        $userAlertRepo->markUserAlertsReadForContent('chat_message', $ids);

        $maxMessageId = max($ids);
        $lastMessage = $this->em()->find('BS\RealTimeChat:Message', $maxMessageId);

        $newRead = $lastMessage->message_date_ ?? null;

        $room->getMember(\XF::visitor())->touchLastViewDate($newRead);

        $this->getRoomRepo()
            ->updateMembersUnreadCount($room->room_id);

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function setupMessage(Room $room)
    {
        $visitor = \XF::visitor();
        $message = $this->plugin('XF:Editor')->convertToBbCode(
            $this->filter('message', 'str')
        );

        /** @var Creator $messageCreator */
        $messageCreator = $this->service('BS\RealTimeChat:Message\Creator', $room, $visitor);
        $messageCreator->setMessageContent($message);

        if ($room->canUploadAttachments()) {
            $messageCreator->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $messageCreator;
    }

    public function actionPost(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $room = $this->assertAccessibleChatRoom($params->tag);

        if (! $room->canPostMessage()) {
            return $this->noPermission();
        }

        if ($messageId = $this->filter('message_id', 'uint')) {
            $params->offsetSet('message_id', $messageId);
            return $this->rerouteController(MessageController::class, 'save', $params);
        }

        $this->assertNotFlooding('chatMessage', $this->options()->realTimeChatSendTimeout);

        $messageCreator = $this->setupMessage($room);
        if (! $messageCreator->validate($errors)) {
            return $this->error($errors);
        }

        $message = $messageCreator->save();

        if ($message) {
            $this->afterPost($messageCreator);
        }

        $response = $this->redirect($this->getDynamicRedirect());
        $response->setJsonParams([
            'insertedMessage' => [
                'id'       => $message->message_id ?? 0,
                'date'     => $message->message_date_ ?? 0,
                'room_tag' => $room->tag
            ]
        ]);
        return $response;
    }

    public function actionTyping(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $room = $this->assertAccessibleChatRoom($params->tag);

        $visitor = \XF::visitor();

        if (! $room->canPostMessage()) {
            return $this->noPermission();
        }

        Broadcast::userTyping($visitor, $params->tag);

        return $this->view();
    }

    protected function setupRoomCreator()
    {
        /** @var \BS\RealTimeChat\Service\Room\Creator $creator */
        $creator = $this->service('BS\RealTimeChat:Room\Creator', null, \XF::visitor());

        $input = $this->filter([
            'description'                => 'str',
            'tag'                        => 'str',
            'allow_messages_from_others' => 'bool'
        ]);
        $input['tag'] = \XF::visitor()->username.'/'.$input['tag'];

        $creator->setupFromInput(
            $input,
            $this->request->getFile('avatar')
        );

        return $creator;
    }


    public function actionCreateRoom()
    {
        $this->assertRegistrationRequired();

        if (! \XF::visitor()->canCreateChatRoom()) {
            return $this->noPermission();
        }

        $creator = $this->setupRoomCreator();
        if (! $creator->validate($errors)) {
            return $this->error($errors, 400);
        }

        $creator->save();

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionCommands(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $room = $this->assertAccessibleChatRoom($params->tag);

        $q = ltrim($this->filter('q', 'str', ['no-trim']));
        $commands = $this->getChatCommandRepo()->getExecutableCommandsForRoom($room, $q);

        return $this->view(
            'BS\RealTimeChat:Room\CommandsList',
            '',
            compact('commands', 'q')
        );
    }

    public function actionPopup()
    {
        $this->assertNotEmbeddedImageRequest();

        $tag = $this->filter('tag', 'str');

        $latestMessageDate = 0;

        $attachmentData = $this->getReplyAttachmentData();

        return $this->view(
            'BS\RealTimeChat:Popup',
            'rtc_popup',
            compact('attachmentData', 'latestMessageDate', 'tag')
        );
    }

    protected function canUpdateSessionActivity(
        $action,
        ParameterBag $params,
        AbstractReply &$reply,
        &$viewState
    ) {
        $validView = true;

        if ($reply->getResponseCode() >= 400) {
            $validView = false;
        }

        $viewState = $validView ? 'valid' : 'error';

        return in_array($action, ['Post', 'Typing']);
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('rtc_chatting');
    }
}

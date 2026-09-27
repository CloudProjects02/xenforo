<?php

namespace BS\RealTimeChat\Pub\Controller;

use BS\RealTimeChat\Concerns\Repos;
use BS\RealTimeChat\MessageType\BanForm;
use BS\RealTimeChat\Pub\Controller\Concerns\Language;
use BS\RealTimeChat\Service\Room\Editor;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Message extends AbstractController
{
    use Concerns\Rooms;
    use Concerns\Messages;
    use Concerns\RequestLimitations;
    use Repos;
    use Language;

    protected function preDispatchController($action, ParameterBag $params)
    {
        if ($this->options()->rtcDisable) {
            throw $this->exception($this->notFound());
        }

        if ($params->tag) {
            $params->tag = RoomTag::normalize($params->tag);
        }

        $this->assertAccessibleChatRoom($params->tag);
    }

    public function actionTo(ParameterBag $params)
    {
        $message = $this->assertMessageExists($params->message_id);
        if (! $message->canView()) {
            return $this->noPermission();
        }

        $filter = [
            'around_message_id' => $message->message_id,
        ];

        return $this->redirect($this->buildLink(
            'chat',
            $message,
            compact('filter')
        ));
    }

    public function actionDelete(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $message = $this->assertMessageExists($params->message_id);

        if (! $message->canDelete()) {
            return $this->noPermission();
        }

        $message->delete();

        return $this->redirect($this->getDynamicRedirect());
    }

    /**
     * @param  \BS\RealTimeChat\Entity\Message  $message
     * @return \XF\Service\AbstractService|\BS\RealTimeChat\Service\Message\Editor
     */
    protected function setupEdit(\BS\RealTimeChat\Entity\Message $message)
    {
        $visitor = \XF::visitor();

        $content = $this->plugin('XF:Editor')->convertToBbCode(
            $this->filter('message', 'str')
        );

        /** @var \BS\RealTimeChat\Service\Message\Editor $messageEditor */
        $messageEditor = $this->service('BS\RealTimeChat:Message\Editor', $message, $visitor);
        $messageEditor->setMessageContent($content);

        if ($message->Room->canUploadAttachments()) {
            $messageEditor->setAttachmentHash($this->filter('attachment_hash', 'str'));
        }

        return $messageEditor;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $message = $this->assertMessageExists($params->message_id);

        if (! $message->canEdit()) {
            return $this->noPermission();
        }

        $messageEditor = $this->setupEdit($message);
        if (! $messageEditor->validate($errors)) {
            return $this->error($errors);
        }

        $messageEditor->save();

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionEdit(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $message = $this->assertMessageExists($params->message_id);

        if (! $message->canEdit()) {
            return $this->noPermission();
        }

        return $this->view(
            'BS\RealTimeChat:Message\Edit',
            '',
            compact('message'),
        );
    }

    public function actionReport(ParameterBag $params)
    {
        $message = $this->assertMessageExists($params->message_id);
        if (! $message->canReport($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\ControllerPlugin\Report $reportPlugin */
        $reportPlugin = $this->plugin('XF:Report');
        return $reportPlugin->actionReport(
            'chat_message',
            $message,
            $this->buildLink('chat/messages/report', $message),
            $this->getDynamicRedirect()
        );
    }

    public function actionReact(ParameterBag $params)
    {
        $message = $this->assertMessageExists($params->message_id);
        if (! $message->canReact($error)) {
            return $this->noPermission($error);
        }

        return $this->plugin('XF:Reaction')
            ->actionReactSimple($message, 'chat/messages');
    }

    public function actionReactions(ParameterBag $params)
    {
        $message = $this->assertMessageExists($params->message_id);
        if (! $message->canView()) {
            return $this->noPermission();
        }

        return $this->plugin('XF:Reaction')->actionReactions(
            $message,
            'chat/messages/reactions'
        );
    }

    public function actionQuote(ParameterBag $params)
    {
        $message = $this->assertMessageExists($params->message_id);
        if (! $message->canView()) {
            return $this->noPermission();
        }

        return $this->plugin('XF:Quote')
            ->actionQuote($message, 'chat:message');
    }


    public function actionTranslate(ParameterBag $params)
    {
        $message = $this->assertMessageExists($params->message_id);
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

    /**
     * @param  \BS\RealTimeChat\Entity\Room  $room
     * @return \BS\RealTimeChat\Service\Room\Ban
     */
    protected function setupRoomBanner(\BS\RealTimeChat\Entity\Room $room)
    {
        /** @var \BS\RealTimeChat\Service\Room\Ban $banner */
        $banner = $this->service('BS\RealTimeChat:Room\Ban', $room);
        return $banner;
    }

    public function actionBan(ParameterBag $params)
    {
        $this->assertPostOnly();

        $message = $this->assertMessageExists($params->message_id);

        $visitor = \XF::visitor();
        if ($visitor->user_id !== $message->user_id) {
            return $this->noPermission();
        }

        $room = $message->Room;

        $username = $this->filter('username', 'str');
        /** @var \XF\Entity\User $user */
        $user = $this->em()->findOne('XF:User', compact('username'));
        if (! $user) {
            return $this->notFound(\XF::phrase('requested_user_not_found'));
        }

        if (! $visitor->canBanUserInChatRoom($room, $user)) {
            return $this->noPermission();
        }

        $reason = $this->filter('reason', 'str');

        $banner = $this->setupRoomBanner($room);
        $banner->banOnMessage(
            $message,
            $user,
            $reason,
            $this->getBanEndDateFromInput()
        );

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function getBanEndDateFromInput(): int
    {
        $input = $this->filter([
            'ban_length'   => 'str',
            'unban_date'   => 'datetime',
            'length_value' => 'uint',
            'length_unit'  => 'str'
        ]);

        switch ($input['ban_length']) {
            case 'permanent':
                return 0;
                break;

            case 'while':
                $lengthValue = $input['length_value'];
                $lengthUnit = $input['length_unit'];
                return strtotime("+$lengthValue $lengthUnit");
                break;

            default:
                return $input['unban_date'];
        }
    }

    public function actionLiftBan(ParameterBag $params)
    {
        $this->assertPostOnly();

        $message = $this->assertMessageExists($params->message_id);

        $visitor = \XF::visitor();
        if ($visitor->user_id !== $message->user_id) {
            return $this->noPermission();
        }

        $room = $message->Room;

        $username = $this->filter('username', 'str');
        /** @var \XF\Entity\User $user */
        $user = $this->em()->findOne('XF:User', compact('username'));
        if (! $user) {
            return $this->notFound(\XF::phrase('requested_user_not_found'));
        }

        if (! $visitor->canLiftBanUserInChatRoom($room, $user)) {
            return $this->noPermission();
        }

        $banner = $this->setupRoomBanner($room);
        $banner->liftBanOnMessage($message, $user);

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function setupRoomEditor(\BS\RealTimeChat\Entity\Room $room)
    {
        /** @var \BS\RealTimeChat\Service\Room\Editor $editor */
        $editor = $this->service('BS\RealTimeChat:Room\Editor', $room);
        $editor->setupFromInput(
            $this->filter([
                'description' => 'str',
                'allow_messages_from_others' => 'bool',
                'delete_avatar' => 'bool'
            ]),
            $this->request->getFile('avatar')
        );

        return $editor;
    }

    protected function postRoomEdit(\BS\RealTimeChat\Entity\Message $message, Editor $editor)
    {
        $message->delete();

        $notifyCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $message->Room,
            \XF::phrase('rtc_room_has_been_edited_by_user_x', [
                'user' => $message->User->username
            ])
        );
        $notifyCreator->setUser($message->User);
        $notifyCreator->save();
    }

    public function actionUpdateRoom(ParameterBag $params)
    {
        $this->assertPostOnly();

        $message = $this->assertMessageExists($params->message_id);

        $visitor = \XF::visitor();
        if ($visitor->user_id !== $message->user_id) {
            return $this->noPermission();
        }

        $room = $message->Room;

        if (! $room->canEdit($visitor)) {
            return $this->noPermission();
        }

        $roomEditor = $this->setupRoomEditor($room);

        if (! $roomEditor->validate($errors)) {
            return $this->error($errors);
        }

        $roomEditor->save();

        $this->postRoomEdit($message, $roomEditor);

        return $this->redirect($this->getDynamicRedirect());
    }

    public function actionSwitchBanListPage(ParameterBag $params)
    {
        $this->assertXhrOnly();
        $this->assertPostOnly();

        $message = $this->assertMessageExists($params->message_id);

        $visitor = \XF::visitor();
        if ($visitor->user_id !== $message->user_id) {
            return $this->noPermission();
        }

        $room = $message->Room;

        if (! isset($message->extra_data['list'])
            || strpos($message->message, '/ban') === false
        ) {
            return $this->noPermission();
        }

        $page = $this->filterPage();

        $this->assertValidPage(
            $page,
            BanForm::BANS_PER_PAGE,
            $this->getRoomRepo()->countBansForRoom($room),
            'chat/messages/switch-ban-list-page',
            $message
        );

        $message->updateExtraData('page', $page);
        $message->save();

        return $this->redirect($this->getDynamicRedirect());
    }

    protected function postRoomWallpaperUpdated(
        \BS\RealTimeChat\Entity\Message $message,
        Editor $editor
    ) {
        $message->delete();

        if ($this->filter('delete_wallpaper', 'bool')) {
            // don't notify if wallpaper was deleted
            return;
        }

        $notifyCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $message->Room,
            \XF::phrase('rtc_you_have_updated_wallpaper_for_this_room')
        );
        $notifyCreator->setUser($message->User);
        $notifyCreator->setPmToUser($message->User);
        $notifyCreator->save();
    }

    public function actionWallpaper(ParameterBag $params)
    {
        $this->assertPostOnly();

        $message = $this->assertMessageExists($params->message_id);

        $visitor = \XF::visitor();
        if ($visitor->user_id !== $message->user_id) {
            return $this->noPermission();
        }

        $room = $message->Room;

        if (! ($room->canSetWallpaper($visitor)
            || $room->canSetIndividualWallpaper($visitor))
        ) {
            return $this->noPermission();
        }

        /** @var \BS\RealTimeChat\Service\Room\Editor $roomEditor */
        $roomEditor = $this->service('BS\RealTimeChat:Room\Editor', $room);

        $wallpaper = $this->request->getFile('wallpaper');
        $forRoom = $this->filter('for_room', 'bool');
        $deleteWallpaper = $this->filter('delete_wallpaper', 'bool');
        $resetWallpaper = $this->filter('reset_wallpaper', 'bool');
        $resetRoomWallpaper = $this->filter('reset_room_wallpaper', 'bool');

        if ($resetWallpaper && ! $room->canResetWallpaper()) {
            return $this->noPermission();
        }

        if ($resetRoomWallpaper && ! $room->canEdit()) {
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

        $this->postRoomWallpaperUpdated($message, $roomEditor);

        return $this->redirect($this->getDynamicRedirect());
    }
}

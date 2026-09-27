<?php

namespace BS\RealTimeChat\Pub\Controller;

use BS\RealTimeChat\Concerns\Repos;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Room extends AbstractController
{
    use Concerns\Rooms;
    use Concerns\RequestLimitations;
    use Repos;

    protected function preDispatchController($action, ParameterBag $params)
    {
        if ($this->options()->rtcDisable) {
            throw $this->exception($this->notFound());
        }

        if ($params->tag) {
            $params->tag = RoomTag::normalize($params->tag);
        }
    }

    public function actionIndex()
    {
        $this->assertXhrOnly();

        if (! \XF::visitor()->canViewChat()) {
            return $this->noPermission();
        }

        $filter = $this->filter([
            'filter' => [
                'withListInfo'       => 'bool',
                'tag'                => 'str',
                'from_date'          => 'uint',
                'after_date'         => 'uint',
                'around_room_tag'    => 'str',
                'pinned'             => 'bool',
                'latest_pinned_date' => 'uint',
            ]
        ])['filter'];

        $jsonResponse = [];

        $roomRepo = $this->getRoomRepo();

        $rooms = $roomRepo->getRoomsForUser(\XF::visitor(), $filter, $jsonResponse);

        $response = $this->view(
            'BS\RealTimeChat:Rooms',
            'real_time_chat_rooms',
            compact('rooms', 'filter')
        );
        $response->setJsonParams($jsonResponse);
        return $response;
    }

    public function actionMenu(ParameterBag $params)
    {
        $this->assertXhrOnly();

        $room = $this->assertAccessibleChatRoom($params->tag);

        return $this->view(
            'BS\RealTimeChat:Room\Menu',
            'rtc_room_menu',
            compact('room')
        );
    }

    public function actionDelete(ParameterBag $params)
    {
        $room = $this->assertAccessibleChatRoom($params->tag);

        if (! $room->canDelete()) {
            return $this->noPermission();
        }

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $room,
            $this->buildLink('chat/rooms/delete', $room),
            $this->buildLink('chat/rooms', $room),
            $this->buildLink('chat'),
            $room->tag,
            'rtc_delete_confirm'
        );
    }
}

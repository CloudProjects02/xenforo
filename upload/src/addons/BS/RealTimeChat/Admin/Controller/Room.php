<?php

namespace BS\RealTimeChat\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Room extends AbstractController
{
    private const ROOMS_PER_PAGE = 20;

    public function actionIndex(ParameterBag $params)
    {
        if ($params->room_id) {
            return $this->rerouteController(__CLASS__, 'edit', $params);
        }

        $page = $this->filterPage();
        $perPage = self::ROOMS_PER_PAGE;

        $roomsFinder = $this->finder('BS\RealTimeChat:Room')
            ->order([
                ['pinned', 'DESC'],
                ['pin_order', 'ASC'],
                ['room_id', 'DESC']
            ])
            ->limitByPage($page, $perPage);

        $total = $roomsFinder->total();

        $rooms = $roomsFinder->fetch();

        return $this->view(
            'BS\RealTimeChat:Room\List',
            'rtc_room_list',
            compact('rooms', 'total', 'page', 'perPage')
        );
    }

    protected function roomAddEdit(\BS\RealTimeChat\Entity\Room $room)
    {
        return $this->view(
            'BS\RealTimeChat:Room\Edit',
            'rtc_room_edit',
            compact('room')
        );
    }

    public function actionEdit(ParameterBag $params)
    {
        $room = $this->assertRoomExists($params->room_id);

        return $this->roomAddEdit($room);
    }

    public function actionAdd(ParameterBag $params)
    {
        $room = $this->em()->create('BS\RealTimeChat:Room');
        $room->user_id = \XF::visitor()->user_id;

        return $this->roomAddEdit($room);
    }

    protected function setupRoomCreator()
    {
        /** @var \BS\RealTimeChat\Service\Room\Creator $creator */
        $creator = $this->service('BS\RealTimeChat:Room\Creator', null, \XF::visitor());
        $creator->setupFromInput(
            $this->filter([
                'description'   => 'str',
                'tag'           => 'str',
                'pinned'        => 'bool',
                'pin_order'     => 'uint',
                'type'          => 'str',
                'delete_avatar' => 'bool'
            ]),
            $this->request->getFile('avatar')
        );

        return $creator;
    }

    protected function setupRoomEditor(\BS\RealTimeChat\Entity\Room $room)
    {
        /** @var \BS\RealTimeChat\Service\Room\Editor $editor */
        $editor = $this->service('BS\RealTimeChat:Room\Editor', $room);
        $editor->setupFromInput(
            $this->filter([
                'description'   => 'str',
                'tag'           => 'str',
                'pinned'        => 'bool',
                'pin_order'     => 'uint',
                'type'          => 'str',
                'delete_avatar' => 'bool'
            ]),
            $this->request->getFile('avatar')
        );

        return $editor;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        /** @var \BS\RealTimeChat\Entity\Room $room */
        $room = $params->room_id
            ? $this->assertRoomExists($params->room_id)
            : $this->em()->create('BS\RealTimeChat:Room');

        $creatorEditor = $room->isInsert()
            ? $this->setupRoomCreator()
            : $this->setupRoomEditor($room);

        if (! $creatorEditor->validate($errors)) {
            return $this->error($errors);
        }

        $creatorEditor->save();

        return $this->redirect($this->buildLink('chat/rooms'));
    }

    public function actionDelete(ParameterBag $params)
    {
        $room = $this->assertRoomExists($params->room_id);

        if (! $room->isDeletable()) {
            return $this->noPermission();
        }

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $room,
            $this->buildLink('chat/rooms/delete', $room),
            $this->buildLink('chat/rooms', $room),
            $this->buildLink('chat/rooms'),
            $room->tag
        );
    }

    public function actionGetNewLink(ParameterBag $params)
    {
        $room = $this->assertRoomExists($params->room_id);

        if (! $room->isMemberType()) {
            return $this->noPermission();
        }

        $link = $room->getNewRoomLink(\XF::visitor());
        $link->save();

        return $this->view(
            'BS\RealTimeChat:Room\NewLink',
            'rtc_room_new_link',
            compact('room', 'link')
        );
    }

    /**
     * @param $id
     * @param $with
     * @param $phraseKey
     * @return \XF\Mvc\Entity\Entity|\BS\RealTimeChat\Entity\Room
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertRoomExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('BS\RealTimeChat:Room', $id, $with, $phraseKey);
    }
}

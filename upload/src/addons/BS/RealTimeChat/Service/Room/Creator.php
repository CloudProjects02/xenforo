<?php

namespace BS\RealTimeChat\Service\Room;

use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Service\Room\Concerns\RoomEditor;
use XF\Service\AbstractService;

class Creator extends AbstractService
{
    use RoomEditor;

    protected function afterSave()
    {
        $this->joinUser();

        $this->postRoomLink();

        Broadcast::newRoom($this->user, $this->room);
    }

    protected function joinUser()
    {
        /** @var \BS\RealTimeChat\Service\Room\Join $joiner */
        $joiner = $this->service(
            'BS\RealTimeChat:Room\Join',
            $this->room,
            $this->room->User
        );
        $joiner->join();
    }

    public function postRoomLink()
    {
        $link = $this->room->getNewRoomLink($this->user);
        $link->save();

        $creator = $this->getMessageRepo()->setupSystemMessageCreator(
            $this->room,
            \XF::phrase('rtc_room_invite_link_x', [
                'link' => $this->app->router('public')->buildLink('canonical:chat/l', $link)
            ])
        );
        $creator->setUser($this->user);
        $saved = $creator->save();

        $this->room->getMember($this->user)
            ->touchLastViewDate(microtime(true) * 1000);

        return $saved;
    }
}

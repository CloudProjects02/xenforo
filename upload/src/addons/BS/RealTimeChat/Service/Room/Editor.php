<?php

namespace BS\RealTimeChat\Service\Room;

use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Service\Room\Concerns\RoomEditor;
use XF\Service\AbstractService;

class Editor extends AbstractService
{
    use RoomEditor {
        __construct as protected __constructRoomEditor;
    }

    public function __construct(
        \XF\App $app,
        Room $room,
        ?\XF\Entity\User $user = null
    ) {
        parent::__construct($app);
        $this->__constructRoomEditor($app, $room, $user);
    }
}
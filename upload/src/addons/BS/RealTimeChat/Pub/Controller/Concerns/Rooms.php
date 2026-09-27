<?php

namespace BS\RealTimeChat\Pub\Controller\Concerns;

trait Rooms
{
    /**
     * @return \BS\RealTimeChat\Entity\Room
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertAccessibleChatRoom($tag)
    {
        if (! $tag) {
            throw $this->exception($this->error(
                \XF::phrase('rtc_requested_chat_room_not_found'),
                404
            ));
        }

        /** @var \BS\RealTimeChat\Entity\Room $room */
        $room = $this->em()->findOne('BS\RealTimeChat:Room', compact('tag'));
        if (! $room) {
            throw $this->exception($this->error(
                \XF::phrase('rtc_requested_chat_room_not_found'),
                404
            ));
        }

        if (! \XF::visitor()->canViewChatRoom($room, $error)) {
            throw $this->exception($this->error(
                $error ?? \XF::phrase('rtc_you_have_not_access_to_this_chat_room'),
                403
            ));
        }

        return $room;
    }
}

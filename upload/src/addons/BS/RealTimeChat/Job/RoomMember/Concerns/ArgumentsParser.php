<?php

namespace BS\RealTimeChat\Job\RoomMember\Concerns;

trait ArgumentsParser
{
    protected function getMember()
    {
        $em = $this->app->em();

        $memberId = $this->data['member_id'] ?? null;

        if ($memberId) {
            /** @var \BS\RealTimeChat\Entity\RoomMember $member */
            $member = $em->find('BS\RealTimeChat:RoomMember', $memberId);
            return $member;
        }

        $roomId = $this->data['room_id'];
        $userId = $this->data['user_id'];

        /** @var \BS\RealTimeChat\Entity\Room $room */
        $room = $em->find('BS\RealTimeChat:Room', $roomId);
        if (!$room) {
            return null;
        }

        /** @var \XF\Entity\User $user */
        $user = $em->find('XF:User', $userId);
        if (!$user) {
            return null;
        }

        return $room->getMember($user);
    }
}

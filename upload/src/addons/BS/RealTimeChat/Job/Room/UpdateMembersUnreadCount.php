<?php

namespace BS\RealTimeChat\Job\Room;

use BS\RealTimeChat\DB;
use XF\Job\AbstractJob;

class UpdateMembersUnreadCount extends AbstractJob
{
    public static function create(int $roomId)
    {
        \XF::app()->jobManager()->enqueueUnique(
            'rtc_upd_room_unread:' . $roomId,
            'BS\RealTimeChat:Room\UpdateMembersUnreadCount',
            [
                'room_id' => $roomId,
            ]
        );
    }

    public function run($maxRunTime)
    {
        $roomId = $this->data['room_id'];

        $completed = false;

        DB::repeatOnDeadlock(static function ($db) use ($roomId, &$completed) {
            $db->query("
                SELECT *
                FROM xf_bs_chat_room_member
                WHERE room_id = ?
                FOR UPDATE
            ", [$roomId]);

            $db->query("
                UPDATE xf_bs_chat_room_member as member
                SET unread_count = (
                    SELECT COUNT(*)
                    FROM xf_bs_chat_message
                    WHERE room_id = ?
                      AND message_date > member.last_view_date
                      AND (pm_user_id = 0 OR pm_user_id = member.user_id)
                )
                WHERE room_id = ?
            ", array_fill(0, 2, $roomId));

            $completed = true;
        });

        if (!$completed) {
            return $this->resume();
        }

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Updating chat room members unread count...';
    }

    public function canTriggerByChoice()
    {
        return false;
    }

    public function canCancel()
    {
        return false;
    }
}

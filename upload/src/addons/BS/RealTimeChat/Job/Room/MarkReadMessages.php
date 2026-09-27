<?php

namespace BS\RealTimeChat\Job\Room;

use BS\RealTimeChat\DB;
use XF\Job\AbstractJob;

class MarkReadMessages extends AbstractJob
{
    public static function create(int $roomId, int $userId, int $beforeDate)
    {
        \XF::app()->jobManager()->enqueueUnique(
            'rtc_mark_read:' . $roomId . ':' . $userId,
            'BS\RealTimeChat:Room\MarkReadMessages',
            [
                'room_id' => $roomId,
                'user_id' => $userId,
                'before_date' => $beforeDate,
            ]
        );
    }

    public function run($maxRunTime)
    {
        $roomId = $this->data['room_id'];
        $userId = $this->data['user_id'];
        $beforeDate = $this->data['before_date'];

        $completed = false;

        DB::repeatOnDeadlock(static function ($db) use ($roomId, $userId, $beforeDate, &$completed) {
            $db->query("
                SELECT *
                FROM xf_bs_chat_message
                WHERE room_id = ?
                  AND user_id != ?
                  AND message_date <= ?
                  AND has_been_read = 0
                FOR UPDATE
            ", [$roomId, $userId, $beforeDate]);

            $db->update(
                'xf_bs_chat_message',
                ['has_been_read' => 1],
                'room_id = ? AND user_id != ? AND message_date <= ? AND has_been_read = 0',
                [$roomId, $userId, $beforeDate]
            );

            $completed = true;
        });

        if (! $completed) {
            return $this->resume();
        }

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Marking messages as read...';
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

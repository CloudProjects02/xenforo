<?php

namespace BS\XFMessenger\Job\Conversation;

use BS\RealTimeChat\DB;
use XF\Job\AbstractJob;

class MarkReadMessages extends AbstractJob
{
    public static function create(int $conversationId, int $userId, int $beforeDate)
    {
        \XF::app()->jobManager()->enqueueUnique(
            'xfm_mark_read:' . $conversationId . ':' . $userId,
            'BS\XFMessenger:Conversation\MarkReadMessages',
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'before_date' => $beforeDate,
            ]
        );
    }

    public function run($maxRunTime)
    {
        $conversationId = $this->data['conversation_id'];
        $userId = $this->data['user_id'];
        $beforeDate = $this->data['before_date'];

        $completed = false;

        DB::repeatOnDeadlock(static function ($db) use ($conversationId, $userId, $beforeDate, &$completed) {
            $db->query("
                SELECT *
                FROM xf_conversation_message
                WHERE conversation_id = ?
                  AND user_id != ?
                  AND message_date <= ?
                  AND xfm_has_been_read = 0
                FOR UPDATE
            ", [$conversationId, $userId, $beforeDate]);

            $db->update(
                'xf_conversation_message',
                ['xfm_has_been_read' => 1],
                'conversation_id = ? AND user_id != ? AND message_date <= ? AND xfm_has_been_read = 0',
                [$conversationId, $userId, $beforeDate]
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

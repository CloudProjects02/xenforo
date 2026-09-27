<?php

namespace BS\XFMessenger\Job\Conversation;

use BS\RealTimeChat\DB;
use XF\Job\AbstractJob;

class UpdateMembersUnreadCount extends AbstractJob
{
    public static function create(int $conversationId, ?int $forUserId = null)
    {
        \XF::app()->jobManager()->enqueueUnique(
            'xfm_upd_conv_unread:' . $conversationId . ($forUserId ? ':' . $forUserId : ''),
            'BS\XFMessenger:Conversation\UpdateMembersUnreadCount',
            [
                'conversation_id' => $conversationId,
                'for_user_id' => $forUserId,
            ]
        );
    }

    public function run($maxRunTime)
    {
        $conversationId = $this->data['conversation_id'];
        $forUserId = $this->data['for_user_id'] ?? null;

        $completed = false;

        DB::repeatOnDeadlock(function ($db) use ($conversationId, $forUserId, &$completed) {
            if ($forUserId) {
                $this->rebuildForUser($db, $conversationId, $forUserId);
            } else {
                $this->rebuildForConversation($db, $conversationId);
            }

            $completed = true;
        });

        if (!$completed) {
            return $this->resume();
        }

        return $this->complete();
    }

    protected function rebuildForConversation($db, int $conversationId)
    {
        $db->query("
            SELECT *
            FROM xf_conversation_user
            WHERE conversation_id = ?
            FOR UPDATE
        ", [$conversationId]);

        $db->query("
            UPDATE xf_conversation_user as cu
            LEFT JOIN xf_conversation_recipient as recipient 
              ON (recipient.conversation_id = cu.conversation_id AND recipient.user_id = cu.owner_user_id)
            SET cu.unread_count = (
                SELECT COUNT(*)
                FROM xf_conversation_message as cm
                WHERE cm.conversation_id = cu.conversation_id
                  AND cm.message_date > recipient.last_read_date
            ) 
            WHERE cu.conversation_id = ?
        ", [$conversationId]);
    }

    protected function rebuildForUser($db, int $conversationId, int $userId)
    {
        $db->query("
            SELECT *
            FROM xf_conversation_user
            WHERE conversation_id = ?
              AND owner_user_id = ?
            FOR UPDATE
        ", [$conversationId, $userId]);

        $db->query("
            UPDATE xf_conversation_user as cu
            LEFT JOIN xf_conversation_recipient as recipient 
              ON (recipient.conversation_id = cu.conversation_id AND recipient.user_id = cu.owner_user_id)
            SET cu.unread_count = (
                SELECT COUNT(*)
                FROM xf_conversation_message as cm
                WHERE cm.conversation_id = cu.conversation_id
                  AND cm.message_date > recipient.last_read_date
            ) 
            WHERE cu.conversation_id = ?
              AND cu.owner_user_id = ?
        ", [$conversationId, $userId]);
    }

    public function getStatusMessage()
    {
        return 'Updating conversation members unread count...';
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

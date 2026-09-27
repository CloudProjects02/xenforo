<?php

namespace BS\XFMessenger\Repository;

use XF\Mvc\Entity\Repository;

class Attachment extends Repository
{
    public function findUserConversationAttachments(\XF\Entity\User $user, string $q)
    {
        $results = $this->db()->fetchAll("
            SELECT attachment_id
            FROM xf_attachment
            WHERE content_type = 'conversation_message'
              AND content_id IN (
                  SELECT message_id
                    FROM xf_conversation_message
                    WHERE attach_count > 0
                      AND conversation_id IN (
                          SELECT conversation_id
                          FROM xf_conversation_user
                          WHERE owner_user_id = ?
                      )
              )
            LIMIT 1000
        ", [$user->user_id]);

        $attachmentIds = array_column($results, 'attachment_id');

        $attachmentFinder = $this->finder('XF:Attachment')
            ->where('attachment_id', $attachmentIds);

        if (mb_strlen($q) > 2) {
            $attachmentFinder->where('Data.filename', 'LIKE', '%' . $q . '%');
        }

        $attachmentFinder->order('attach_date', 'DESC');

        return $attachmentFinder;
    }
}
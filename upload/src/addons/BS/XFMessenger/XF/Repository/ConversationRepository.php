<?php

namespace BS\XFMessenger\XF\Repository;

class ConversationRepository extends XFCP_ConversationRepository
{
    public function markUserConversationRead(
        \XF\Entity\ConversationUser $userConv,
        $newRead = null
    ) {
        if (! $userConv->Master) {
            return;
        }

        $newRead ??= \XF::$time;

        $markRecipient = ! empty($userConv->Recipient);
        $markUser = ($userConv->is_unread && $newRead >= $userConv->Master->last_message_date);

        if (! $markRecipient && ! $markUser) {
            return;
        }

        if ($markRecipient) {
            $userConv->Recipient->touchLastRead($newRead);
        }

        if ($markUser) {
            $userConv->is_unread = false;
            $userConv->save(false, false);
        }
    }

    // no way to keep parent method
    public function markUserConversationUnread(\XF\Entity\ConversationUser $userConv)
    {
        if (! $userConv->Master) {
            return;
        }

        $markRecipient = ($userConv->Recipient && $userConv->Recipient->last_read_date > 0);
        $markUser = ! $userConv->is_unread;

        if (! $markRecipient && ! $markUser) {
            return;
        }

        $this->db()->beginTransaction();

        if ($markRecipient) {
            /** @var \XF\Entity\ConversationMessage $lastNotVisitorMessage */
            $lastNotVisitorMessage = $this->finder('XF:ConversationMessage')
                ->where('conversation_id', $userConv->conversation_id)
                ->where('user_id', '!=', $userConv->owner_user_id)
                ->order('message_date', 'DESC')
                ->fetchOne();

            $messageDate = $lastNotVisitorMessage->message_date ?? 0;
            if ($messageDate) {
                // I take one second to make the message unread
                --$messageDate;
            }

            $userConv->Recipient->last_read_date = $messageDate;
            $userConv->Recipient->save(false, false);
        }

        if ($markUser) {
            $userConv->is_unread = true;
            $userConv->save(false, false);
        }

        $this->db()->commit();
    }
}

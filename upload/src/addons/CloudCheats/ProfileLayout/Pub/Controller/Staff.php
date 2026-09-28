<?php

namespace CloudCheats\ProfileLayout\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Staff extends AbstractController
{
    public function actionJoinConversation(ParameterBag $params)
    {
        $this->assertPostOnly();

        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('general', 'warn') &&
            !$visitor->hasPermission('general', 'manageAnyUser'))
        {
            return $this->noPermission();
        }

        $reportId = $this->filter('report_id', 'uint');

        /** @var \XF\Entity\Report $report */
        $report = $this->em()->find(\XF\Entity\Report::class, $reportId);
        if (!$report || $report->content_type !== 'conversation_message')
        {
            return $this->error(\XF::phrase('requested_page_not_found'));
        }

        $conversationId = $report->content_info['conversation_id'] ?? null;
        if (!$conversationId)
        {
            return $this->error('Could not determine conversation ID from report.');
        }

        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $this->em()->find(\XF\Entity\ConversationMaster::class, $conversationId);
        if (!$conversation)
        {
            return $this->error(\XF::phrase('requested_page_not_found'));
        }

        // Check if already a participant
        $existing = $this->em()->findOne(\XF\Entity\ConversationUser::class, [
            'conversation_id' => $conversationId,
            'user_id'         => $visitor->user_id,
        ]);

        if (!$existing)
        {
            // Add staff member as participant via DB (bypasses ownership check)
            $db = \XF::db();
            $db->insert('xf_conversation_user', [
                'conversation_id' => $conversationId,
                'user_id'         => $visitor->user_id,
                'recipient_state' => 'active',
                'unread_date'     => \XF::$time,
                'last_read_date'  => 0,
            ], false, 'recipient_state = VALUES(recipient_state)');

            // Update conversation recipient count
            $db->query(
                'UPDATE xf_conversation_master SET recipient_count = recipient_count + 1 WHERE conversation_id = ?',
                [$conversationId]
            );
        }

        $msg = 'You have joined the conversation as staff.';
        if ($this->request->isXhr())
        {
            return $this->message($msg);
        }

        return $this->redirect(
            $this->buildLink('conversations', $conversation),
            $msg
        );
    }

    protected function assertPostOnly(): void
    {
        if (!$this->request->isPost())
        {
            throw $this->exception($this->error(\XF::phrase('action_not_permitted'), 403));
        }
    }
}

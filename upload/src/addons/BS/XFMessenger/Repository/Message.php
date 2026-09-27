<?php

namespace BS\XFMessenger\Repository;

use BS\XFMessenger\Job\Conversation\MarkReadMessages;
use XF\Entity\ConversationMaster;
use XF\Finder\ConversationMessage;
use XF\Mvc\Entity\Repository;

class Message extends Repository
{
    /**
     * @param  array  $jsonResponse
     * @param  \XF\Mvc\Entity\ArrayCollection|\XF\Entity\ConversationMessage[]  $messages
     * @param  \XF\Finder\ConversationMessage  $messageFinder
     * @param  array  $filter
     * @return void
     */
    public function extendListJsonResponse(
        array &$jsonResponse,
        $messages,
        \XF\Finder\ConversationMessage $messageFinder,
        array $filter
    ) {
        if (! empty($filter['withListInfo'])) {
            if (! $messages->count()) {
                $jsonResponse['hasBefore'] = false;
                $jsonResponse['hasAfter'] = false;
            } else {
                $jsonResponse['hasBefore'] = (clone $messageFinder)
                        ->where('message_id', '<', $messages->first()->message_id)
                        ->total() > 0;

                $jsonResponse['hasAfter'] = (clone $messageFinder)
                        ->where('message_id', '>', $messages->last()->message_id)
                        ->total() > 0;
            }
        }

        $jsonResponse['latestMessageDate'] = 0;
        if ($messages->count()) {
            $jsonResponse['latestMessageDate'] = $messages->last()->message_date_;
        }
    }

    public function filterMessages(ConversationMessage $messageFinder, array $filter)
    {
        $pageLimited = false;

        if (! empty($filter['from_date'])) {
            $messageFinder->where('message_date', '>', $filter['from_date']);
        }

        if (! empty($filter['message_id'])) {
            $messageFinder->where('message_id', $filter['message_id']);
        }

        if (! empty($filter['around_message_id'])) {
            /** @var \XF\Entity\ConversationMessage $message */
            $message = $this->em->find('XF:ConversationMessage', $filter['around_message_id']);
            if ($message) {
                $position = $this->getMessagePosition($message);
                $perPage = $this->options()->xfmMessagesPerPage;
                $page = ceil($position / $perPage);

                $messageFinder->limitByPage($page, $perPage);
                $pageLimited = true;
            }
        }

        if (! empty($filter['start_message_id'])) {
            $messageFinder->where('message_id', '>', $filter['start_message_id']);
        }

        if (! empty($filter['after_message_id'])) {
            $messageFinder->where('message_id', '>=', $filter['after_message_id']);
        }

        if (! empty($filter['end_message_id'])) {
            $messageFinder->where('message_id', '<', $filter['end_message_id']);
        }

        if (! empty($filter['page'])) {
            $messageFinder->limitByPage($filter['page'], $this->options()->xfmMessagesPerPage);
            $pageLimited = true;
        }

        if (! $pageLimited) {
            $messageFinder->limit($this->options()->xfmMessagesPerPage);
        }
    }

    public function getOrderWrappedFromFilter(array $filter)
    {
        $order = ['message_date', 'desc'];
        $sort = static fn ($messages) => $messages->reverse();

        if (! empty($filter['start_message_id'])) {
            $order = ['message_date', 'asc'];
            $sort = static fn ($messages) => $messages;
        }

        return compact('order', 'sort');
    }

    public function getMessagePosition(\XF\Entity\ConversationMessage $message): int
    {
        return (int)$this->db()->fetchOne(
            "
            SELECT COUNT(*)
            FROM xf_conversation_message
            WHERE message_id >= ?
              AND conversation_id = ?
        ", [$message->message_id, $message->conversation_id]);
    }

    public function markReadMessagesBeforeDate(
        ConversationMaster $conversation,
        \XF\Entity\User $user,
        int $date
    ) {
        MarkReadMessages::create($conversation->conversation_id, $user->user_id, $date);
    }
}

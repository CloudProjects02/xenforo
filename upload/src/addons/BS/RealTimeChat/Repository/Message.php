<?php

namespace BS\RealTimeChat\Repository;

use BS\RealTimeChat\Job\Room\MarkReadMessages;
use XF\Mvc\Entity\Repository;

class Message extends Repository
{
    /**
     * @param \BS\RealTimeChat\Entity\Room  $room
     * @param  string  $text
     * @return \BS\RealTimeChat\Service\Message\Creator
     */
    public function setupSystemMessageCreator(
        \BS\RealTimeChat\Entity\Room $room,
        string $text
    ) {
        /** @var \BS\RealTimeChat\Service\Message\Creator $creator */
        $creator = $this->app()->service('BS\RealTimeChat:Message\Creator', $room);
        $creator->setType('system');
        $creator->setMessageContent($text);

        return $creator;
    }

    /**
     * @param  \BS\RealTimeChat\Entity\Room  $room
     * @param  \XF\Entity\User  $author
     * @param  string  $text
     * @return \BS\RealTimeChat\Service\Message\Creator
     */
    public function setupUserMessageCreator(
        \BS\RealTimeChat\Entity\Room $room,
        \XF\Entity\User $author,
        string $text
    ) {
        /** @var \BS\RealTimeChat\Service\Message\Creator $creator */
        $creator = $this->app()->service('BS\RealTimeChat:Message\Creator', $room);
        $creator->setType('bubble');
        $creator->setUser($author);
        $creator->setMessageContent($text);

        return $creator;
    }

    public function findMessages(\XF\Entity\User $forUser = null)
    {
        $limit = $this->options()->realTimeChatMessageLimit;

        return $this->finder('BS\RealTimeChat:Message')
            ->skipIgnored($forUser)
            ->skipPm($forUser)
            ->order('message_date', 'desc')
            ->with(['User', 'PmUser', 'ToUser'])
            ->limit($limit);
    }

    /**
     * @param  \BS\RealTimeChat\Entity\Room  $room
     * @param  array  $filter
     * @param  array  $jsonResponse
     * @return \XF\Mvc\Entity\ArrayCollection|\BS\RealTimeChat\Entity\Message[]
     */
    public function getMessagesForList(
        \BS\RealTimeChat\Entity\Room $room,
        array $filter = [],
        array &$jsonResponse = []
    ) {
        $limit = $this->options()->realTimeChatMessageLimit;

        /** @var \BS\RealTimeChat\Finder\Message $messageFinder */
        $messageFinder = $this->finder('BS\RealTimeChat:Message')
            ->inRoom($room)
            ->skipIgnored()
            ->skipPm()
            ->with(['User', 'PmUser', 'ToUser', 'Reactions|'.\XF::visitor()->user_id])
            ->limit($limit);

        $this->filterMessages($messageFinder, $filter);

        $wrappedOrder = $this->getOrderWrappedFromFilter($filter);
        $messageFinder->order($wrappedOrder['order']);

        $messages = $messageFinder->fetch();

        $messages = $wrappedOrder['sort']($messages);

        $this->extendListJsonResponse($jsonResponse, $messages, $messageFinder, $filter);

        return $messages;
    }

    /**
     * @param  array  $jsonResponse
     * @param  \XF\Mvc\Entity\ArrayCollection|\BS\RealTimeChat\Entity\Message[]  $messages
     * @param  \BS\RealTimeChat\Finder\Message  $messageFinder
     * @param  array  $filter
     * @return void
     */
    public function extendListJsonResponse(
        array &$jsonResponse,
        $messages,
        \BS\RealTimeChat\Finder\Message $messageFinder,
        array $filter = []
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

    public function filterMessages(\BS\RealTimeChat\Finder\Message $messageFinder, array $filter)
    {
        if (! empty($filter['from_date'])) {
            $messageFinder->where('message_date', '>', $filter['from_date']);
        }

        if (! empty($filter['message_id'])) {
            $messageFinder->where('message_id', $filter['message_id']);
        }

        if (! empty($filter['around_message_id'])) {
            /** @var \BS\RealTimeChat\Entity\Message $message */
            $message = $this->em->find('BS\RealTimeChat:Message', $filter['around_message_id']);
            if ($message) {
                $position = $this->getMessagePosition($message);
                $perPage = $this->options()->realTimeChatMessageLimit;
                $page = ceil($position / $perPage);

                $messageFinder->limitByPage($page, $perPage);
            }
        }

        if (! empty($filter['start_message_id'])) {
            $messageFinder->where('message_id', '>', $filter['start_message_id']);
        }

        if (! empty($filter['end_message_id'])) {
            $messageFinder->where('message_id', '<', $filter['end_message_id']);
        }

        if (! empty($filter['page'])) {
            $messageFinder->limitByPage($filter['page'], $this->options()->realTimeChatMessageLimit);
        }
    }

    protected function getOrderWrappedFromFilter(array $filter)
    {
        $order = [['message_date', 'desc'], ['message_id', 'desc']];
        $sort = static fn ($messages) => $messages->reverse();

        if (! empty($filter['start_message_id'])) {
            $order = [['message_date', 'asc'], ['message_id', 'asc']];
            $sort = static fn ($messages) => $messages;
        }

        return compact('order', 'sort');
    }

    public function fetchMessagesForChatGpt(
        \BS\RealTimeChat\Entity\Message $beforeMessage,
        \XF\Entity\User $bot,
        int $limit
    ) {
        $messagesFinder = $this->findMessages($bot)
            ->where('room_id', '=', $beforeMessage->room_id)
            ->where('message_date', '<=', $beforeMessage->message_date_)
            ->order('message_date', 'desc')
            ->limit($limit);

        $messages = $messagesFinder->fetch()->reverse();

        /** @var \BS\ChatGPTBots\Repository\Message $gptFrameworkMessagesRepo */
        $gptFrameworkMessagesRepo = $this->repository('BS\ChatGPTBots:Message');
        return array_values(
            array_map(static function ($message) use ($bot, $gptFrameworkMessagesRepo) {
                if ($bot) {
                    $role = $bot->user_id === $message['user_id']
                        ? 'assistant'
                        : 'user';
                } else {
                    $role = 'user';
                }
                return $gptFrameworkMessagesRepo->wrapMessage($message['message'], $role);
            }, $messages->toArray())
        );
    }

    public function getMessagePosition(\BS\RealTimeChat\Entity\Message $message): int
    {
        return (int)$this->db()->fetchOne(
            "
            SELECT COUNT(*)
            FROM xf_bs_chat_message
            WHERE message_id >= ?
              AND room_tag = ?
        ", [$message->message_id, $message->room_tag]);
    }

    public function updateMessagesRoomTag(string $oldTag, string $newTag)
    {
        $this->db()->update(
            'xf_bs_chat_message',
            ['room_tag' => $newTag],
            'room_tag = ?',
            $oldTag
        );
    }

    public function markReadMessagesBeforeDate(
        \BS\RealTimeChat\Entity\Room $room,
        \XF\Entity\User $user,
        int $date
    ) {
        MarkReadMessages::create($room->room_id, $user->user_id, $date);
    }
}
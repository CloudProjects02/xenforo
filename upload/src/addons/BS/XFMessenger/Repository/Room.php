<?php

namespace BS\XFMessenger\Repository;

use XF\Mvc\Entity\Repository;

class Room extends Repository
{
    /**
     * @param  array  $jsonResponse
     * @param  \XF\Mvc\Entity\AbstractCollection|\XF\Entity\ConversationUser  $rooms
     * @param  \XF\Mvc\Entity\Finder  $roomFinder
     * @param  array  $filter
     * @return void
     */
    public function extendListJsonResponse(
        array &$jsonResponse,
        $rooms,
        $roomFinder,
        array $filter = []
    ) {
        $lastMessageDates = $rooms->pluckNamed('last_message_date');

        if (! empty($filter['withListInfo'])) {
            $jsonResponse['hasBefore'] = false;
            $jsonResponse['hasAfter'] = false;

            $roomFinder = $this->removeLastMessageConditionsFromRoomFinder($roomFinder);

            if ($rooms->count()) {
                $jsonResponse['hasBefore'] = (clone $roomFinder)
                        ->where('last_message_date', '>', max($lastMessageDates))
                        ->total() > 0;

                $jsonResponse['hasAfter'] = (clone $roomFinder)
                        ->where('last_message_date', '<', min($lastMessageDates))
                        ->total() > 0;
            }
        }

        $jsonResponse['latestRoomDate'] = count($lastMessageDates)
            ? min($lastMessageDates)
            : 0;
    }

    protected function removeLastMessageConditionsFromRoomFinder(
        \XF\Mvc\Entity\Finder $roomFinder
    ) {
        $roomFinder = clone $roomFinder;

        $conditions = $roomFinder->getConditions();
        $conditions = array_filter($conditions, static function (string $expression) {
            return strpos($expression, 'last_message_date') === false;
        });

        $roomFinder->resetWhere();

        foreach ($conditions as $condition) {
            $roomFinder->whereSql($condition);
        }

        return $roomFinder;
    }

    public function findUserConversations(\XF\Entity\User $user, string $q, array $filter = [])
    {
        $em = $this->em;

        $q = trim($q);

        $finder = $this->repository('XF:Conversation')
            ->findUserConversations($user, false);

        if (mb_strlen($q) > 2) {
            $finder->where('Master.title', 'LIKE', '%' . $q . '%');
        }

        if (! empty($filter['starter'])) {
            /** @var \XF\Entity\User|null $starter */
            $starter = $em->findOne('XF:User', ['username' => $filter['starter']]);

            if ($starter) {
                $finder->where('Master.user_id', $starter->user_id);
            }
        }

        if (! empty($filter['receiver'])) {
            /** @var \XF\Entity\User|null $receiver */
            $receiver = $em->findOne('XF:User', ['username' => $filter['receiver']]);

            if ($receiver) {
                $finder->exists('Master.Recipients|' . $receiver->user_id);
            }
        }

        if (! empty($filter['starred'])) {
            $finder->where('is_starred', true);
        }

        if (! empty($filter['unread'])) {
            $finder->where('is_unread', true);
        }

        return $finder;
    }

    public function filterConversations(
        \XF\Mvc\Entity\Finder $conversationFinder,
        array $filter
    ) {
        if (! empty($filter['from_date'])) {
            $conversationFinder->where(
                'last_message_date',
                '<',
                $filter['from_date']
            );
        }

        if (! empty($filter['after_date'])) {
            $conversationFinder->where(
                'last_message_date',
                '>',
                $filter['after_date']
            );
        }

        if (! empty($filter['around_room_tag'])) {
            /** @var \XF\Entity\ConversationUser|null $conversation */
            $conversation = $this->finder('XF:ConversationUser')
                ->forUser(\XF::visitor(), false)
                ->where('conversation_id', (int)$filter['around_room_tag'])
                ->fetchOne();

            if ($conversation) {
                $conversationFinder->where(
                    'last_message_date',
                    '<=',
                    $conversation->last_message_date
                );
            }
        }

        if (! empty($filter['tag'])) {
            $conversationFinder->where('conversation_id', (int)$filter['tag']);
        }
    }
}

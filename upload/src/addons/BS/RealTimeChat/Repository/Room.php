<?php

namespace BS\RealTimeChat\Repository;

use BS\RealTimeChat\Job\Room\UpdateMembersUnreadCount;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Mvc\Entity\Repository;

class Room extends Repository
{
    public const ROOMS_LIMIT = 20;

    public function getPinnedRoomsForUser(\XF\Entity\User $user, array $filter)
    {
        $userId = $user->user_id;
        $memberRelation = 'Members|'.$userId;
        $banRelation = 'Bans|'.$userId;

        $roomFinder = $this->finder('BS\RealTimeChat:Room')
            ->with([$memberRelation, 'LastMessage', 'LastMessage.User', $banRelation])
            ->whereOr([
                [$memberRelation.'.user_id', $userId],
                ['type', 'public']
            ])
            ->where($banRelation.'.ban_id', null)
            ->onlyPinned($user)
            ->order([
                ['pinned', 'DESC'],
                ['pin_order', 'ASC'],
                [$memberRelation.'.room_pinned', 'DESC'],
                [$memberRelation.'.room_pin_order', 'ASC'],
                ['last_message_date', 'DESC'],
            ]);

        if (! empty($filter['latest_pinned_date'])) {
            $roomFinder->where('last_message_date', '>', $filter['latest_pinned_date']);
        }

        return $roomFinder->fetch();
    }

    /**
     * @param  \XF\Entity\User  $user
     * @param  array  $filter
     * @param  array  $jsonResponse
     * @return \XF\Mvc\Entity\AbstractCollection|\BS\RealTimeChat\Entity\Room[]
     */
    public function getRoomsForUser(
        \XF\Entity\User $user,
        array $filter = [],
        array &$jsonResponse = []
    ) {
        $limit = self::ROOMS_LIMIT;
        $userId = $user->user_id;
        $memberRelation = 'Members|'.$userId;
        $banRelation = 'Bans|'.$userId;

        $roomFinder = $this->finder('BS\RealTimeChat:Room')
            ->with([$memberRelation, 'LastMessage', 'LastMessage.User', $banRelation])
            ->whereOr([
                [$memberRelation.'.user_id', $userId],
                ['type', 'public']
            ])
            ->where($banRelation.'.ban_id', null)
            ->limit($limit);

        $wrappedOrder = $this->getOrderWrappedFromFilter($filter);
        $roomFinder->order($wrappedOrder['order']);

        $targetRoomTag = ($filter['tag'] ?? null) ?: ($filter['around_room_tag'] ?? null);

        $roomFinder->skipPinned($user, array_filter([$targetRoomTag]));

        $this->filterRooms($roomFinder, $filter, $memberRelation);

        $rooms = $wrappedOrder['sort']($roomFinder->fetch());

        $this->extendListJsonResponse($jsonResponse, $rooms, $roomFinder, $filter);

        $shouldIncludePinnedRooms = ! empty($filter['withListInfo'])
            && ! $jsonResponse['hasBefore']
            && empty($filter['from_date']);

        if ($shouldIncludePinnedRooms) {
            $pinnedRooms = $this->getPinnedRoomsForUser($user, $filter);
            // need to unique rooms
            // because pinned rooms can be in the list of rooms
            $rooms = new \XF\Mvc\Entity\ArrayCollection($this->uniqueArrayWithKey([
                ...array_values($pinnedRooms->toArray()),
                ...array_values($rooms->toArray())
            ], 'room_id'));
        }

        return $rooms;
    }

    protected function uniqueArrayWithKey(array $array, string $key): array
    {
        $added = [];
        $items = [];

        foreach ($array as $item) {
            if (isset($added[$item[$key]])) {
                continue;
            }

            $items[] = $item;
            $added[$item[$key]] = true;
        }

        return $items;
    }

    /**
     * @param  array  $jsonResponse
     * @param  \XF\Mvc\Entity\AbstractCollection|\BS\RealTimeChat\Entity\Room[]  $rooms
     * @param  \XF\Mvc\Entity\Finder  $roomFinder
     * @param  array  $filter
     * @param  \XF\Entity\User|null  $forUser
     * @return void
     */
    public function extendListJsonResponse(
        array &$jsonResponse,
        $rooms,
        $roomFinder,
        array $filter = [],
        \XF\Entity\User $forUser = null
    ) {
        $forUser ??= \XF::visitor();

        $lastMessageDates = $rooms->pluckNamed('last_message_date');

        if (! empty($filter['withListInfo'])) {
            $jsonResponse['hasBefore'] = false;
            $jsonResponse['hasAfter'] = false;

            $roomFinder = $this->removeLimitationConditionsFromRoomFinder($roomFinder)
                ->skipPinned($forUser);

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

    protected function removeLimitationConditionsFromRoomFinder(
        \XF\Mvc\Entity\Finder $roomFinder
    ) {
        $roomFinder = clone $roomFinder;

        $conditions = $roomFinder->getConditions();
        $conditions = array_filter($conditions, static function (string $expression) {
            return strpos($expression, 'last_message_date') === false
                && strpos($expression, 'tag') === false;
        });

        $roomFinder->resetWhere();

        foreach ($conditions as $condition) {
            $roomFinder->whereSql($condition);
        }

        return $roomFinder;
    }

    protected function filterRooms(
        \XF\Mvc\Entity\Finder $roomFinder,
        array $filter,
        string $memberRelation
    ) {
        if (! empty($filter['from_date'])) {
            $roomFinder->where(
                'last_message_date',
                '<',
                $filter['from_date']
            );
        }

        if (! empty($filter['after_date'])) {
            $roomFinder->where(
                'last_message_date',
                '>',
                $filter['after_date']
            );
        }

        if (! empty($filter['around_room_tag'])) {
            /** @var \BS\RealTimeChat\Entity\Room $room */
            $room = $this->em->findOne('BS\RealTimeChat:Room', ['tag' => $filter['around_room_tag']]);

            $introduceFilter = function () use ($room, $roomFinder) {
                /** @var \BS\RealTimeChat\Entity\Room|null $topRoom */
                $topRoom = $this->finder('BS\RealTimeChat:Room')
                    ->where('last_message_date', '>=', $room->last_message_date)
                    ->where('room_id', '!=', $room->room_id)
                    ->order(['last_message_date', 'asc'])
                    ->limit(self::ROOMS_LIMIT / 2)
                    ->fetch()
                    ->last();

                /** @var \BS\RealTimeChat\Entity\Room|null $bottomRoom */
                $bottomRoom = $this->finder('BS\RealTimeChat:Room')
                    ->where('last_message_date', '<=', $room->last_message_date)
                    ->where('room_id', '!=', $room->room_id)
                    ->order(['last_message_date', 'desc'])
                    ->limit(self::ROOMS_LIMIT / 2)
                    ->fetch()
                    ->last();

                if (! $topRoom || ! $bottomRoom) {
                    return;
                }

                $roomFinder->where([
                    ['last_message_date', '<=', $topRoom->last_message_date],
                    ['last_message_date', '>=', $bottomRoom->last_message_date],
                ]);
            };

            if (null !== $room) {
                $introduceFilter();
            }
        }

        if (! empty($filter['tag'])) {
            $roomFinder->where('tag', RoomTag::normalize($filter['tag']));
        }
    }

    protected function getOrderWrappedFromFilter(array $filter)
    {
        $order = [['last_message_date', 'desc'], ['room_id', 'desc']];
        $sort = static fn ($rooms) => $rooms;

        return compact('order', 'sort');
    }

    public function updateMembers(\BS\RealTimeChat\Entity\Room $room)
    {
        $room->clearCache('Members');

        $members = $room->Members;
        $membersCount = $members->count();
        $memberNames = $members->pluckNamed('user_id');

        $room->members = $memberNames;
        $room->members_count = $membersCount;

        $room->saveIfChanged();
    }

    public function updateMembersUnreadCount(int $roomId)
    {
        UpdateMembersUnreadCount::create($roomId);
    }

    public function cleanUpAfterDeleteRoom(\BS\RealTimeChat\Entity\Room $room)
    {
        /** @var \BS\RealTimeChat\Service\Room\Avatar $avatarService */
        $avatarService = $this->app()->service('BS\RealTimeChat:Room\Avatar', $room);
        $avatarService->deleteAvatarForRoomDelete();

        /** @var \BS\RealTimeChat\Service\Room\Wallpaper $wallpaperService */
        $wallpaperService = $this->app()->service('BS\RealTimeChat:Room\Wallpaper', $room);
        $wallpaperService->deleteWallpapersForRoomDelete();

        $roomId = $room->room_id;

        $db = $this->db();
        $db->delete('xf_bs_chat_room_link', 'room_id = ?', $roomId);
        $db->delete('xf_bs_chat_room_member', 'room_id = ?', $roomId);
        $db->delete('xf_bs_chat_message', 'room_id = ?', $roomId);
        $db->delete('xf_bs_chat_ban', 'room_id = ?', $roomId);
    }

    public function countBansForRoom(\BS\RealTimeChat\Entity\Room $room)
    {
        return $this->db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_bs_chat_ban
            WHERE room_id = ?
        ", $room->room_id);
    }
}

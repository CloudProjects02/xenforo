<?php

namespace BS\RealTimeChat\Job\RoomMember;

use BS\RealTimeChat\DB;
use BS\RealTimeChat\Job\RoomMember\Concerns\ArgumentsParser;
use XF\Job\AbstractJob;

class UpdateWallpaper extends AbstractJob
{
    use ArgumentsParser;

    public static function create(int $roomId, int $userId, array $options)
    {
        \XF::app()->jobManager()->enqueueUnique(
            'rtc_update_wallpaper:'.$roomId.':'.$userId,
            'BS\RealTimeChat:RoomMember\UpdateWallpaper',
            array_merge([
                'room_id' => $roomId,
                'user_id' => $userId
            ], $options)
        );
    }

    public function run($maxRunTime)
    {
        $member = $this->getMember();
        if (! $member) {
            return $this->complete();
        }

        $date = $this->data['date'] ?? null;
        $options = $this->data['options'] ?? null;

        if ($date) {
            $member->room_wallpaper_date = $date;
        }

        if ($options) {
            $member->room_wallpaper_options = $options;
        }

        $completed = false;

        DB::repeatOnDeadlock(static function () use ($member, &$completed) {
            if (!$member->lockForUpdate()) {
                return;
            }

            $member->saveIfChanged();

            $completed = true;
        });

        if ($completed) {
            return $this->complete();
        }

        return $this->resume();
    }

    public function getStatusMessage()
    {
        return 'Updating wallpaper...';
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

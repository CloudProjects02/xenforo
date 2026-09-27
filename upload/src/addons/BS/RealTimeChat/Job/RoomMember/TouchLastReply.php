<?php

namespace BS\RealTimeChat\Job\RoomMember;

use BS\RealTimeChat\DB;
use BS\RealTimeChat\Job\RoomMember\Concerns\ArgumentsParser;
use BS\RealTimeChat\Utils\Date;
use XF\Job\AbstractJob;

class TouchLastReply extends AbstractJob
{
    use ArgumentsParser;

    public static function create(int $roomId, int $userId, ?int $date)
    {
        $date ??= Date::getMicroTimestamp();

        \XF::app()->jobManager()->enqueueUnique(
            'rtc_touch_last_reply:'.$roomId.':'.$userId,
            'BS\RealTimeChat:RoomMember\TouchLastReply',
            [
                'room_id' => $roomId,
                'user_id' => $userId,
                'date'    => $date
            ]
        );
    }

    public function run($maxRunTime)
    {
        $member = $this->getMember();
        $date = $this->data['date'];

        if (!$member) {
            return $this->complete();
        }

        $completed = false;

        DB::repeatOnDeadlock(static function () use ($member, $date, &$completed) {
            if (!$member->lockForUpdate()) {
                return;
            }

            $member->last_reply_date = $date;

            $member->save();

            $completed = true;
        });

        if (! $completed) {
            return $this->resume();
        }

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Touching last reply date for room member...';
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

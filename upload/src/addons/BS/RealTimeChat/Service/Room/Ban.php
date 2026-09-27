<?php

namespace BS\RealTimeChat\Service\Room;

use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Concerns\Repos;
use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Entity\Room;
use XF\Entity\User;
use XF\Service\AbstractService;

class Ban extends AbstractService
{
    use Repos;

    protected Room $room;

    public function __construct(\XF\App $app, Room $room)
    {
        parent::__construct($app);
        $this->room = $room;
    }

    public function banOnMessage(
        Message $message,
        User $user,
        string $reason = '',
        int $unbanDate = 0
    ) {
        /** @var \BS\RealTimeChat\Entity\Ban $ban */
        $ban = $this->em()->create('BS\RealTimeChat:Ban');
        $ban->user_id = $user->user_id;
        $ban->room_id = $this->room->room_id;
        $ban->ban_user_id = $message->user_id;
        $ban->date = \XF::$time;
        $ban->unban_date = $unbanDate;
        $ban->reason = $reason;

        $ban->save();

        $message->delete();

        $notifyCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $this->room,
            \XF::phrase('rtc_user_x_has_been_banned', [
                'user' => $user->username
            ])
        );
        $notifyCreator->save();
    }

    public function liftBanOnMessage(
        Message $message,
        User $user
    ) {
        $message->Room->Bans[$user->user_id]->delete();

        $message->delete();

        $notifyCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $this->room,
            \XF::phrase('rtc_user_x_has_been_unbanned', [
                'user' => $user->username
            ])
        );
        $notifyCreator->save();
    }
}

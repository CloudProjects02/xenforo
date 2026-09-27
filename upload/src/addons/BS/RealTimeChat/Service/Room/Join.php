<?php

namespace BS\RealTimeChat\Service\Room;

use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Entity\RoomLink;
use BS\RealTimeChat\Entity\RoomMember;
use XF\Service\AbstractService;

class Join extends AbstractService
{
    protected Room $room;
    protected \XF\Entity\User $user;
    protected RoomMember $member;

    public function __construct(\XF\App $app, Room $room, \XF\Entity\User $user = null)
    {
        parent::__construct($app);
        $this->room = $room;
        $this->user = $user ?? \XF::visitor();
        $this->member = $this->room->getMember($this->user);
    }

    public function join()
    {
        $this->member->save();
        return $this->member;
    }

    public function leave()
    {
        $this->member->delete();
        return true;
    }

    public function joinFromLink(RoomLink $link)
    {
        $this->member->invited_by_user_id = $link->user_id;
        $this->member->invite_type = 'link';
        $this->member->save();
        return $this->member;
    }
}
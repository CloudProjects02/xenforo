<?php

namespace BS\RealTimeChat\Concerns;

trait Repos
{
    /**
     * @return \BS\RealTimeChat\Repository\Room
     */
    protected function getRoomRepo()
    {
        return \XF::repository('BS\RealTimeChat:Room');
    }

    /**
     * @return \BS\RealTimeChat\Repository\Message
     */
    protected function getMessageRepo()
    {
        return \XF::repository('BS\RealTimeChat:Message');
    }

    /**
     * @return \BS\RealTimeChat\Repository\Command
     */
    protected function getChatCommandRepo()
    {
        return \XF::repository('BS\RealTimeChat:Command');
    }
}
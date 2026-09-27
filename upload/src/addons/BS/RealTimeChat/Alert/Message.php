<?php

namespace BS\RealTimeChat\Alert;

use XF\Alert\AbstractHandler;

class Message extends AbstractHandler
{
    public function getOptOutActions()
    {
        if (! \XF::options()->rtcEnableAlerts) {
            return [];
        }

        return [
            'mention',
            'quote',
            'private_message',
            'reaction'
        ];
    }

    public function getOptOutDisplayOrder()
    {
        return 250;
    }
}

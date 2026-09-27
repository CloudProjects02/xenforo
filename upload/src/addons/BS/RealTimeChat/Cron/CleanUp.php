<?php

namespace BS\RealTimeChat\Cron;

class CleanUp
{
    public static function removeExpiredBans()
    {
        $db = \XF::db();

        $db->delete('xf_bs_chat_ban', 'unban_date > 0 AND unban_date <= ?', \XF::$time);
    }
}
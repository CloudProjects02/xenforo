<?php

namespace BS\RealTimeChat;

use XF\Db\DeadlockException;

class DB
{
    public static function repeatOnDeadlock(
        \Closure $callback,
        \Closure $onLimitReached = null,
        int $maxAttempts = 3,
        int $sleep = 2
    ) {
        $db = \XF::db();

        $db->beginTransaction();

        $deadlockAttempts = 0;

        while ($deadlockAttempts < $maxAttempts) {
            try {
                $result = $callback($db);
            } catch (DeadlockException $e) {
                $db->rollBack();

                $deadlockAttempts++;

                if ($deadlockAttempts >= $maxAttempts) {
                    \XF::logException($e, false, "Receive $maxAttempts+ deadlocks in a row:");

                    if ($onLimitReached) {
                        $onLimitReached();
                    }
                }

                sleep($sleep);

                $db->beginTransaction();

                continue;
            }

            $db->commit();

            return $result;
        }

        $db->commit();

        return null;
    }
}

<?php

namespace BS\RealTimeChat\Repository;

use BS\RealTimeChat\ChatCommand\CommandBus;
use XF\Mvc\Entity\Repository;

class Command extends Repository
{
    public function getExecutableCommandsForRoom(\BS\RealTimeChat\Entity\Room $room, string $q)
    {
        // remove / from the beginning of the string
        $q = ltrim($q, '/');

        $testMessage = $room->getNewMessage(\XF::visitor());

        $commands = CommandBus::getCommands(true);

        return array_filter($commands, static function ($command, $name) use ($testMessage, $q) {
            if (! preg_match('/^' . $q . '/', $name)) {
                return false;
            }

            return $command && $command->canExecute($testMessage, [], []);
        }, ARRAY_FILTER_USE_BOTH);
    }
}
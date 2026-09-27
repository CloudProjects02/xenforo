<?php

namespace BS\RealTimeChat\ChatCommand;

class CommandBus
{
    protected static array $commandsInitialized = [];

    public static function getCommands(bool $resolveHandlers = false): array
    {
        $commands = [
            'clear'     => Clear::class,
            'pm'        => Pm::class,
            'to'        => To::class,
            'ban'       => Ban::class,
            'leave'     => Leave::class,
            'edit'      => Edit::class,
            'messages'  => Messages::class,
            'link'      => Link::class,
            'wallpaper' => Wallpaper::class,
        ];

        \XF::fire('rtc_commands', [&$commands]);

        if ($resolveHandlers) {
            $handlers = array_map(static function ($name) {
                return self::resolveCommand($name);
            }, array_keys($commands));
            $commands = array_combine(array_keys($commands), $handlers);
        }

        return $commands;
    }

    public static function resolveCommand(string $name): ?ICommand
    {
        if (isset(self::$commandsInitialized[$name])) {
            return self::$commandsInitialized[$name];
        }

        $commands = self::getCommands();
        if (! isset($commands[$name])) {
            return null;
        }

        $commandClass = \XF::extendClass($commands[$name]);
        if (! class_exists($commandClass)) {
            return null;
        }

        $command = new $commandClass(\XF::app());

        self::$commandsInitialized[$name] = $command;

        return $command;
    }
}

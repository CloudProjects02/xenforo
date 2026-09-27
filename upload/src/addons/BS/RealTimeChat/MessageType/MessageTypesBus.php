<?php

namespace BS\RealTimeChat\MessageType;

class MessageTypesBus
{
    public const DEFAULT_MSG_TYPE = 'bubble';

    public static function getTypes(): array
    {
        $types = [
            'bubble'        => Bubble::class,
            'system'        => System::class,
            'ban_form'      => BanForm::class,
            'edit_room'     => EditRoom::class,
            'command_list'  => CommandList::class,
            'wallpaper'     => Wallpaper::class,
        ];

        \XF::fire('rtc_message_types', [&$types]);

        return $types;
    }

    public static function resolveType(string $name): ?IMessageType
    {
        $types = self::getTypes();
        if (! isset($types[$name])) {
            return null;
        }

        $typeClass = \XF::extendClass($types[$name]);
        if (! class_exists($typeClass)) {
            return null;
        }

        return new $typeClass();
    }
}

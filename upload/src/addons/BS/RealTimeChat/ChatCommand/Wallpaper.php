<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class Wallpaper extends AbstractCommand
{
    public function getName(): string
    {
        return 'wallpaper';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        $room = $message->Room;
        if ($room->canSetWallpaper($message->User)) {
            return true;
        }

        return $room->canSetIndividualWallpaper($message->User);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $message->type = 'wallpaper';
        $message->pm_user_id = $message->user_id;
    }

    public function shouldSaveMessageAfterExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return true;
    }
}

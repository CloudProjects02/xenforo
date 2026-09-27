<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class Help extends AbstractCommand
{
    public function getName(): string
    {
        return 'help';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return true;
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $message->type = 'command_list';
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

<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class Edit extends AbstractCommand
{
    public function getName(): string
    {
        return 'edit';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return $message->Room->canEdit($message->User);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $message->type = 'edit_room';
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
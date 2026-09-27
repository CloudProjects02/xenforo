<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class Leave extends AbstractCommand
{
    public function getName(): string
    {
        return 'leave';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return $message->Room->canLeave($message->User);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $message->Room->getMember($message->User)->delete();
    }

    public function shouldSaveMessageAfterExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return false;
    }
}

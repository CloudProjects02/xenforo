<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

interface ICommand
{
    public function getTitle(): string;

    public function getDescription(): string;

    public function getName(): string;

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool;

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void;

    public function shouldSaveMessageAfterExecute(
        Message $message,
        array $args,
        array $options
    ): bool;
}
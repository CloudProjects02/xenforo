<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;
use XF\Entity\User;

interface IMessageType
{
    public function render(Message $message, array $filter): string;

    public function isTranslatable(Message $message): bool;

    public function isSearchable(Message $message): bool;

    public function translate(Message $message, string $toLanguageCode): void;

    public function canView(Message $message, &$error = ''): bool;

    public function canCopy(Message $message, &$error = ''): bool;

    public function canEdit(Message $message, &$error = ''): bool;

    public function canDelete(Message $message, &$error = ''): bool;

    public function canReport(Message $message, &$error = '', User $asUser = null): bool;

    public function canReact(Message $message, &$error = null): bool;
}

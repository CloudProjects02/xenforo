<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;
use XF\Entity\User;
use XF\Template\Templater;

abstract class AbstractMessageType implements IMessageType
{
    protected Templater $templater;

    public function __construct()
    {
        $this->templater = \XF::app()->templater();
    }

    public function isTranslatable(Message $message): bool
    {
        return false;
    }

    public function isSearchable(Message $message): bool
    {
        return false;
    }

    public function translate(Message $message, string $toLanguageCode): void
    {
    }

    public function canView(Message $message, &$error = ''): bool
    {
        return true;
    }

    public function canCopy(Message $message, &$error = ''): bool
    {
        return false;
    }

    public function canEdit(Message $message, &$error = ''): bool
    {
        return false;
    }

    public function canDelete(Message $message, &$error = ''): bool
    {
        return true;
    }

    public function canReact(Message $message, &$error = null): bool
    {
        return false;
    }

    public function canReport(Message $message, &$error = '', User $asUser = null): bool
    {
        return false;
    }
}

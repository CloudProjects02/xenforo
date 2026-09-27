<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\MessageType\Concerns\BubbleTranslate;
use XF\Entity\User;

class Bubble extends AbstractMessageType
{
    use BubbleTranslate;

    public function render(Message $message, array $filter): string
    {
        return $this->templater->renderMacro(
            'public:rtc_message_macros',
            'type_bubble',
            compact('message', 'filter')
        );
    }

    public function isSearchable(Message $message): bool
    {
        $room = $message->Room;

        if ($message->pm_user_id) {
            return false;
        }

        if ($room->isPublic()) {
            return true;
        }

        if (!$room->allowed_replies && $room->isMemberType()) {
            return true;
        }

        return false;
    }

    public function canEdit(Message $message, &$error = ''): bool
    {
        return true;
    }

    public function canCopy(Message $message, &$error = ''): bool
    {
        return true;
    }

    public function canDelete(Message $message, &$error = ''): bool
    {
        return true;
    }

    public function canReport(
        Message $message,
        &$error = '',
        User $asUser = null
    ): bool {
        return true;
    }

    public function canReact(Message $message, &$error = null): bool
    {
        return true;
    }
}

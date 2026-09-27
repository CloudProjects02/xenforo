<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;

class System extends AbstractMessageType
{
    public function render(Message $message, array $filter): string
    {
        return $this->templater->renderMacro(
            'public:rtc_message_macros',
            'type_system',
            compact('message', 'filter')
        );
    }

    public function canDelete(Message $message, &$error = ''): bool
    {
        return $message->user_id === \XF::visitor()->user_id;
    }
}

<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;

class EditRoom extends AbstractMessageType
{
    public function render(Message $message, array $filter): string
    {
        return $this->templater->renderMacro(
            'public:rtc_message_type_edit_room_macros',
            'type_edit_room',
            compact('message', 'filter')
        );
    }
}
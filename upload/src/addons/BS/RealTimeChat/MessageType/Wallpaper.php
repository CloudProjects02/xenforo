<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\Entity\Message;

class Wallpaper extends AbstractMessageType
{
    public function render(Message $message, array $filter): string
    {
        return $this->templater->renderMacro(
            'public:rtc_message_type_wallpaper_macros',
            'set_wallpaper_form',
            compact('message', 'filter')
        );
    }

    public function canDelete(Message $message, &$error = ''): bool
    {
        return true;
    }
}

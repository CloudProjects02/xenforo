<?php

namespace BS\RealTimeChat\MessageType;

use BS\RealTimeChat\ChatCommand\CommandBus;
use BS\RealTimeChat\Entity\Message;

class CommandList extends AbstractMessageType
{
    public function render(Message $message, array $filter): string
    {
        $commands = CommandBus::getCommands(true);
        ksort($commands, SORT_STRING);

        return $this->templater->renderMacro(
            'public:rtc_message_type_command_list_macros',
            'type_command_list',
            compact('commands', 'message', 'filter')
        );
    }
}
<?php

namespace BS\RealTimeChat\Service\Message;

use BS\RealTimeChat\ChatCommand\CommandBus;
use BS\RealTimeChat\Entity\Message;
use XF\App;
use XF\Service\AbstractService;

class CommandExecutor extends AbstractService
{
    protected array $commands;

    protected Message $message;

    protected array $executedCommand = [];

    public function __construct(App $app, Message $message)
    {
        parent::__construct($app);

        $this->commands = CommandBus::getCommands();
        $this->message = $message;
    }

    public function parseAndExecuteCommand(Creator $creator): void
    {
        $message = $this->message;
        $commands = $this->commands;
        $text = $message->message;

        if (! $text) {
            return;
        }

        if ($text[0] !== '/') {
            return;
        }

        $data = $this->parseCommand($text);
        if (! isset($commands[$data['name']])) {
            return;
        }

        $command = CommandBus::resolveCommand($data['name']);
        if (! $command) {
            return;
        }

        if (! $command->canExecute($message, $data['args'], $data['options'])) {
            return;
        }

        try {
            $command->execute($message, $data['args'], $data['options'], $creator);

            $this->executedCommand = [
                'command' => $command,
                'message' => $message,
                'args'    => $data['args'],
                'options' => $data['options']
            ];
        } catch (\Exception $e) {
            \XF::logException($e, false, '[RTC] Command Execution Error:');
            return;
        }
    }

    public function shouldSaveMessage(): bool
    {
        if (! $this->executedCommand) {
            return true;
        }

        $command = $this->executedCommand['command'];
        return $command->shouldSaveMessageAfterExecute(
            $this->executedCommand['message'],
            $this->executedCommand['args'],
            $this->executedCommand['options']
        );
    }

    protected function parseCommand(string $text)
    {
        $args = [];
        $options = [];

        $text = trim($text);
        $text = substr($text, 1);

        $parts = preg_split('/\s+(?=(?:[^"]*["][^"]*["])*[^"]*$)/', $text);

        $name  = array_shift($parts);

        foreach ($parts as $part) {
            if (strpos($part, '--') === 0) {
                $optionParts = explode('=', $part, 2);
                $optionName = substr($optionParts[0], 2);
                $optionValue = isset($optionParts[1]) ? trim($optionParts[1], '"') : true;

                $options[$optionName] = $optionValue;
            } else {
                $args[] = $part;
            }
        }

        return compact('name', 'args', 'options');
    }
}

<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\ChatCommand\Concerns\UsernameArg;
use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class To extends AbstractCommand
{
    use UsernameArg;

    public function getName(): string
    {
        return 'to';
    }

    public function canExecute(Message $message, array $args, array $options): bool
    {
        $argsStr = implode(' ', $args);
        if (strpos($argsStr, ',') !== false) {
            $args = explode(',', $argsStr);
        }

        $username = $args[0] ?? null;
        return null !== $this->getUserFromUsernameArg($username);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $argsStr = implode(' ', $args);
        if (strpos($argsStr, ',') !== false) {
            $args = explode(',', $argsStr);
        }

        $username = $args[0] ?? null;
        $user = $this->getUserFromUsernameArg($username);

        $message->to_user_id = $user->user_id;
        $creator->setMessageContent(preg_replace_callback(
            $this->buildUsernameCommandRegex($user->username),
            static fn () => "@{$user->username}, ",
            $message->message
        ));
    }

    public function shouldSaveMessageAfterExecute(Message $message, array $args, array $options): bool
    {
        return true;
    }
}

<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;
use XF\Entity\User;

class Ban extends AbstractCommand
{
    public function getName(): string
    {
        return 'ban';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        $user = $message->User;
        if (! $user) {
            return false;
        }

        if (count($options) > 1) {
            return false;
        }

        if (isset($options['list'])) {
            return $message->Room->canViewBannedList($user);
        }

        if (isset($options['lift'])) {
            return $message->Room->canLiftBan($user);
        }

        return $message->Room->canBan($user);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $message->type = 'ban_form';
        $message->pm_user_id = $message->user_id;

        if (isset($options['lift'])) {
            $message->updateExtraData('lift', true);
        }

        if (isset($options['list'])) {
            $message->updateExtraData('list', true);
        }
    }


    public function shouldSaveMessageAfterExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return true;
    }
}

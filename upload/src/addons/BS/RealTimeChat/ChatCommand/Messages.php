<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class Messages extends AbstractCommand
{
    public function getName(): string
    {
        return 'messages';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        $on = $options['on'] ?? null;
        $off = $options['off'] ?? null;

        return $message->Room->canEdit($message->User)
            && ($on !== null || $off !== null);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $on = $options['on'] ?? null;
        $off = $options['off'] ?? null;

        $notificationPhrase = $on !== null
            ? 'rtc_messages_in_the_room_were_turned_on'
            : 'rtc_messages_in_the_room_were_turned_off';

        if ($on !== null) {
            $message->Room->fastUpdate('allowed_replies', true);
        } elseif ($off !== null) {
            $message->Room->fastUpdate('allowed_replies', false);
        }

        $messageCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $message->Room,
            \XF::phrase($notificationPhrase)
        );
        $messageCreator->setPmToUser($message->User);
        $messageCreator->save();
    }

    public function shouldSaveMessageAfterExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return false;
    }
}

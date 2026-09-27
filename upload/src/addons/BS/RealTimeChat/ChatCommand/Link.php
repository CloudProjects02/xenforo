<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Service\Message\Creator;

class Link extends AbstractCommand
{
    public function getName(): string
    {
        return 'link';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        if (count($options) > 1) {
            return false;
        }

        return $message->Room->canGetNewLink($message->User);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $room = $message->Room;

        if (isset($options['refresh'])) {
            $this->db()->delete('xf_bs_chat_room_link', 'room_id = ?', $room->room_id);
        }

        $newLink = $room->getNewRoomLink($message->User);
        $newLink->save();

        $messageCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $room,
            \XF::phrase('rtc_room_invite_link_x', [
                'link' => $newLink->url
            ])
        );
        $messageCreator->setUser($message->User);
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

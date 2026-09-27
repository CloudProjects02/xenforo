<?php

namespace BS\RealTimeChat\ChatCommand;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Service\Message\Creator;

class Clear extends AbstractCommand
{
    public function getName(): string
    {
        return 'clear';
    }

    public function canExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return $message->Room->canClear($message->User);
    }

    public function execute(
        Message $message,
        array $args,
        array $options,
        Creator $creator
    ): void {
        $username = $args[0] ?? $options['user'] ?? null;

        if ($username) {
            $this->executeWithUsername($message, $args, $options, $creator, $username);
            return;
        }

        $this->deleteMessages($message->room_tag);

        $this->updateLastMessageForRoom($message->Room);

        Broadcast::messagesDeleted($message->room_tag, [
            'beforeDate' => \XF::$time
        ]);

        $messageCreator = $this->getMessageRepo()->setupSystemMessageCreator(
            $message->Room,
            \XF::phrase('rtc_history_has_been_cleared')
        );
        $messageCreator->save();
    }

    protected function executeWithUsername(
        Message $message,
        array $args,
        array $options,
        Creator $creator,
        string $username
    ) {
        /** @var \XF\Entity\User $user */
        $user = $this->em()->findOne('XF:User', compact('username'));
        if (! $user) {
            $messageCreator = $this->getMessageRepo()->setupSystemMessageCreator(
                $message->Room,
                \XF::phrase('requested_user_not_found')
            );
            $messageCreator->setPmToUser($message->User);
            $messageCreator->save();
            return;
        }

        $this->deleteUserMessages($user->user_id, $message->room_tag);

        $this->updateLastMessageForRoom($message->Room);

        Broadcast::messagesDeleted($message->room_tag, [
            'userId' => $user->user_id
        ]);
    }

    protected function updateLastMessageForRoom(Room $room)
    {
        $lastMessage = $this->finder('BS\RealTimeChat:Message')
            ->skipPm()
            ->where('room_id', $room->room_id)
            ->order('message_date', 'DESC')
            ->fetchOne();

        $room->updateLastMessage($lastMessage);
        $room->save();
    }

    protected function deleteUserMessages(int $userId, string $roomTag)
    {
        $this->db()->delete(
            'xf_bs_chat_message',
            'user_id = ? and room_tag = ?',
            [$userId, $roomTag]
        );
    }

    protected function deleteMessages(string $roomTag)
    {
        $this->db()->delete('xf_bs_chat_message', 'room_tag = ?', $roomTag);
    }

    public function shouldSaveMessageAfterExecute(
        Message $message,
        array $args,
        array $options
    ): bool {
        return false;
    }
}

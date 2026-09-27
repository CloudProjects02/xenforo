<?php

namespace BS\RealTimeChat\Notifier\Message;

use BS\RealTimeChat\Entity\Message;
use XF\App;
use XF\Entity\User;
use XF\Notifier\AbstractNotifier;

class PrivateMessage extends AbstractNotifier
{
    protected Message $message;

    public function __construct(App $app, Message $message)
    {
        parent::__construct($app);

        $this->message = $message;
    }

    public function canNotify(User $user)
    {
        if (! $this->message->pm_user_id) {
            return false;
        }

        return $user->user_id === $this->message->pm_user_id
            && $user->user_id !== $this->message->user_id;
    }

    public function sendAlert(User $user)
    {
        $message = $this->message;
        return $this->basicAlert(
            $user,
            $message->user_id,
            $message->User->username,
            'chat_message',
            $message->message_id,
            'private_message'
        );
    }
}

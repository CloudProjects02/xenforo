<?php

namespace BS\RealTimeChat\Service\Message;

use XF\Service\AbstractNotifier;

class Notifier extends AbstractNotifier
{
    protected $message;

    public function __construct(\XF\App $app, \BS\RealTimeChat\Entity\Message $message)
    {
        parent::__construct($app);

        $this->message = $message;
    }

    public static function createForJob(array $extraData)
    {
        $message = \XF::app()->find('BS\RealTimeChat:Message', $extraData['messageId']);
        if (! $message) {
            return null;
        }

        return \XF::service('BS\RealTimeChat:Message\Notifier', $message);
    }

    protected function getExtraJobData()
    {
        return [
            'messageId' => $this->message->message_id
        ];
    }

    protected function loadNotifiers()
    {
        return [
            'mention'         => $this->app->notifier('BS\RealTimeChat:Message\Mention', $this->message),
            'quote'           => $this->app->notifier('BS\RealTimeChat:Message\Quote', $this->message),
            'private_message' => $this->app->notifier('BS\RealTimeChat:Message\PrivateMessage', $this->message),
        ];
    }

    public function setPmUserId(int $userId)
    {
        $this->addNotification('private_message', $userId);
    }

    protected function loadExtraUserData(array $users)
    {
    }

    protected function canUserViewContent(\XF\Entity\User $user)
    {
        return \XF::asVisitor(
            $user,
            function () {
                return $this->message->canView();
            }
        );
    }
}

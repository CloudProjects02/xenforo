<?php

namespace BS\RealTimeChat\Service\ChatGPT;

use BS\AIBots\Entity\Bot;
use BS\AIBots\Service\ChatGPT\AbstractReplier;
use BS\RealTimeChat\Service\ChatGPT\Concerns\ChatMessages;

/**
 * @property \BS\RealTimeChat\Entity\Message $replyContextItem
 */
class Replier extends AbstractReplier
{
    use ChatMessages;

    protected function _postReplies(Bot $bot)
    {
        /** @var \BS\RealTimeChat\Service\Message\Creator $messageCreator */
        $messageCreator = $this->service(
            'BS\RealTimeChat:Message\Creator',
            $this->replyContextItem->Room,
            $bot->User
        );
        $messageCreator->setMessageContent($this->getFinalMessage());

        if (! $bot->restrictions['spam_check']) {
            $messageCreator->setIsAutomated();
        }

        if ($this->replyContextItem->isPm()
            || $bot->getSafest('general', 'rtc_respond_in_pm_only')
        ) {
            $messageCreator->setPmToUser($this->replyContextItem->User);
        }

        $message = $messageCreator->save();
        if (! $message) {
            return null;
        }

        $messageCreator->sendNotifications();

        return $message;
    }
}

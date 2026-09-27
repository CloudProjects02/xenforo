<?php

namespace BS\RealTimeChat\Service\ChatGPT;

use BS\AIBots\Entity\Bot;
use BS\AIBots\Service\ChatGPT\AbstractStreamer;
use BS\RealTimeChat\Service\ChatGPT\Concerns\ChatMessages;
use XF\Mvc\Entity\Entity;

/**
 * @property-read \BS\RealTimeChat\Entity\Message $streamingItem
 * @property-read \BS\RealTimeChat\Entity\Message $replyContextItem
 */
class Streamer extends AbstractStreamer
{
    use ChatMessages;

    protected function _publishStreamingItem(Bot $bot, string $message): Entity
    {
        /** @var \BS\RealTimeChat\Service\Message\Creator $messageCreator */
        $messageCreator = $this->service(
            'BS\RealTimeChat:Message\Creator',
            $this->replyContextItem->Room,
            $bot->User
        );
        $messageCreator->setMessageContent($message);

        if (! $bot->restrictions['spam_check']) {
            $messageCreator->setIsAutomated();
        }

        if ($this->replyContextItem->isPm()
            || $bot->getSafest('general', 'rtc_respond_in_pm_only')
        ) {
            $messageCreator->setPmToUser($this->replyContextItem->User);
        }

        $message = $messageCreator->save();

        $messageCreator->sendNotifications();

        return $message;
    }

    protected function _updateStreamingItem(Bot $bot, string $message): void
    {
        /** @var \BS\RealTimeChat\Service\Message\Editor $messageEditor */
        $messageEditor = $this->service(
            'BS\RealTimeChat:Message\Editor',
            $this->streamingItem,
            $bot->User
        );
        $messageEditor->setSilent(true);
        $messageEditor->setMessageContent($message);
        $messageEditor->save();
    }
}

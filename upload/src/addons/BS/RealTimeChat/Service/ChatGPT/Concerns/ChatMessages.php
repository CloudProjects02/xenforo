<?php

namespace BS\RealTimeChat\Service\ChatGPT\Concerns;

use BS\AIBots\Entity\Bot;
use XF\Mvc\Entity\Entity;

trait ChatMessages
{
    protected function buildMessages(Bot $bot, string $context)
    {
        $message = $this->replyContextItem;

        $prompt = $this->getChatPrompt($bot);
        $contextLimit = $bot->general['rtc_context_limit'];
        if (! $contextLimit) {
            return [
                $this->messageRepo->wrapMessage($prompt, 'system'),
                $this->messageRepo->wrapMessage($message->message)
            ];
        }

        return [
            $this->messageRepo->wrapMessage($prompt, 'system'),
            ...$this->repository('BS\RealTimeChat:Message')
                ->fetchMessagesForChatGpt($message, $bot->User, $contextLimit)
        ];
    }

    protected function getChatPrompt(Bot $bot): string
    {
        $tokens = $this->getChatMessageTokens();
        $prompt = $bot->general['rtc_prompt'];
        $prompt = str_replace(array_keys($tokens), array_values($tokens), $prompt);

        if ($bot->general['rtc_smart_ignore']) {
            $prompt .= PHP_EOL . $this->getSmartIgnorePrompt();
        }

        return $prompt;
    }

    protected function getChatMessageTokens(): array
    {
        return [
            '{author}' => $this->replyContextItem->User->username,
            '{date}' => date('Y-m-d'),
            '{time}' => date('H:i'),
        ];
    }

    protected function makePartialQuote(Entity $context, string $content): string
    {
        return '';
    }

    // don't support quotes
    protected function hasQuotes(string $message, int $userId): bool
    {
        return false;
    }
}
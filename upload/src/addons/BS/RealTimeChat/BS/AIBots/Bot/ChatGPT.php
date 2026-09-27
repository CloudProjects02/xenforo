<?php

namespace BS\RealTimeChat\BS\AIBots\Bot;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Broadcasting\Broadcast;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

class ChatGPT extends XFCP_ChatGPT
{
    protected function shouldHandleContext(Entity $context): bool
    {
        if ($context instanceof Message) {
            return $this->shouldHandleChatMessage($context);
        }

        return parent::shouldHandleContext($context);
    }

    protected function shouldHandleChatMessage(Message $message): bool
    {
        if (! in_array('chat', $this->bot->triggers['active_in_context'], true)) {
            return false;
        }

        return true;
    }

    protected function isRepliesLimited(Entity $context, User $author): bool
    {
        if ($context instanceof Message) {
            return $this->isRepliesLimitedForChatMessage($context, $author);
        }

        return parent::isRepliesLimited($context, $author);
    }

    protected function isRepliesLimitedForChatMessage(Message $message, User $author): bool
    {
        /** @var \BS\AIBots\Finder\ReplyLog $repliesFinder */
        $repliesFinder = \XF::finder('BS\AIBots:ReplyLog');

        $repliesPerDay = $author->hasPermission('bsChat', 'aibGptMaxRepliesPerDay');
        if ($repliesPerDay > 0) {
            $repliesCount = $repliesFinder
                ->forThisDay()
                ->toUser($author)
                ->forContentType($message->getEntityContentType())
                ->total();
            if ($repliesCount >= $repliesPerDay) {
                return true;
            }
        }

        return false;
    }

    public function handle(
        User $author,
        string $message,
        Entity $context,
        bool $isFirstHandle = true
    ): ?Entity {
        if ($context instanceof Message) {
            return $this->handleChatMessage($author, $message, $context, $isFirstHandle);
        }

        return parent::handle($author, $message, $context, $isFirstHandle);
    }

    protected function handleChatMessage(
        User $author,
        string $message,
        Message $context,
        bool $isFirstHandle = true
    ): ?Entity {
        $this->broadcastTyping($context->room_tag);

        if ($this->bot->getSafest('general', 'rtc_streaming_mode')) {
            return $this->streamReply($context);
        }

        return $this->plainReply($context);
    }

    protected function plainReply(Message $message)
    {
        /** @var \BS\RealTimeChat\Service\ChatGPT\Replier $replier */
        $replier = $this->service('BS\RealTimeChat:ChatGPT\Replier', $message);
        return $replier->reply($this->bot);
    }

    protected function streamReply(Message $message)
    {
        /** @var \BS\RealTimeChat\Service\ChatGPT\Streamer $streamer */
        $streamer = $this->service('BS\RealTimeChat:ChatGPT\Streamer', $message);
        return $streamer->stream($this->bot);
    }

    protected function broadcastTyping(string $roomTag)
    {
        Broadcast::userTyping($this->bot->User, $roomTag);
    }

    protected function hasContextTriggers(Entity $context): bool
    {
        if ($context instanceof Message) {
            return $this->hasChatMessageTriggers($context);
        }

        return parent::hasContextTriggers($context);
    }

    protected function hasChatMessageTriggers(Message $message): bool
    {
        return $this->wasMentioned($message->message, $this->bot->user_id)
            || $message->pm_user_id === $this->bot->user_id;
    }

    protected function isValidContext(Entity $context): bool
    {
        return parent::isValidContext($context)
            || $context instanceof Message;
    }

    public function verifyGeneral(array &$general): void
    {
        $originalGeneral = $general;
        parent::verifyGeneral($general);

        $general = array_merge($general, [
            'rtc_prompt' => $originalGeneral['rtc_prompt'] ?? '',
            'rtc_context_limit' => (int)($originalGeneral['rtc_context_limit'] ?? 0),
            'rtc_smart_ignore' => (bool)($originalGeneral['rtc_smart_ignore'] ?? false),
            'rtc_streaming_mode' => (bool)($originalGeneral['rtc_streaming_mode'] ?? false),
            'rtc_respond_in_pm_only' => (bool)($originalGeneral['rtc_respond_in_pm_only'] ?? false),
        ]);

        $activeContext = $this->bot->triggers['active_in_context'] ?? [];
        [$contextLimitMin, $contextLimitMax] = $this->getContextLimitsForChatModel(
            $general['chat_model']
        );

        if (! in_array('chat', $activeContext, true)) {
            return;
        }

        if (empty($general['rtc_prompt'])) {
            $this->bot->error(\XF::phrase('bs_ai_bot.chat_gpt_prompts_must_be_filled'));
        }

        if (! $this->isNumberBetween($general['rtc_context_limit'], $contextLimitMin, $contextLimitMax)) {
            $this->bot->error(\XF::phrase('rtc_aib_invalid_chat_context_limit', [
                'min' => $contextLimitMin,
                'max' => $contextLimitMax,
            ]));
        }
    }

    public function setupDefaults(): void
    {
        parent::setupDefaults();

        $this->bot->general = array_merge([
            'rtc_prompt' => '',
            'rtc_context_limit' => 3,
            'rtc_smart_ignore' => false,
            'rtc_streaming_mode' => false,
            'rtc_respond_in_pm_only' => false,
        ], $this->bot->general);
    }
}

<?php

namespace BS\RealTimeChat\MessageType\Concerns;

use BS\RealTimeChat\Entity\Message;

trait BubbleTranslate
{
    public function isTranslatable(Message $message): bool
    {
        $container = \XF::app()->container();

        if (! $container->offsetExists('chatGPT')) {
            return false;
        }

        /** @var \Orhanerday\OpenAi\OpenAi $api */
        $api = $container->offsetGet('chatGPT');
        if (!$api) {
            return false;
        }

        return mb_strlen($message->message) <= 4096;
    }

    public function translate(Message $message, string $toLanguageCode): void
    {
        $translations = $message->extra_data['translations'] ?? [];
        if (isset($translations[$toLanguageCode])
            && empty($translations[$toLanguageCode]['error'])
        ) {
            return;
        }

        /** @var \BS\RealTimeChat\Service\Translator $translator */
        $translator = \XF::service('BS\RealTimeChat:Translator');
        $output = $translator->translateWithOpenAI($message->message, $toLanguageCode);

        if (! isset($output['translated_text'], $output['detected_language_code'])) {
            $message->updateExtraData([
                'translations' => [
                    $toLanguageCode => [
                        'error' => true,
                    ],
                ],
            ]);
            return;
        }

        $message->updateExtraData([
            'translations' => [
                $toLanguageCode => [
                    'message' => $output['translated_text'],
                    'error'   => false,
                ],
            ],
        ]);

        if (! isset($message->extra_data['detected_language_code'])) {
            $message->updateExtraData([
                'detected_language_code' => $output['detected_language_code'],
            ]);
        }
    }
}

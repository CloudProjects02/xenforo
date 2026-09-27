<?php

namespace BS\XFMessenger\Service\ConversationMessage;

use XF\Entity\ConversationMessage;
use XF\Service\AbstractService;

class Translate extends AbstractService
{
    /** @var ConversationMessage|\BS\XFMessenger\XF\Entity\ConversationMessage */
    protected ConversationMessage $message;

    public function __construct(\XF\App $app, ConversationMessage $message)
    {
        parent::__construct($app);

        $this->message = $message;
    }

    public function translate(string $toLanguageCode)
    {
        $message = $this->message;

        if (isset($message->xfm_extra_data['translations'][$toLanguageCode])) {
            return;
        }

        /** @var \BS\RealTimeChat\Service\Translator $translator */
        $translator = \XF::service('BS\RealTimeChat:Translator');
        $output = $translator->translateWithOpenAI($message->message, $toLanguageCode);

        if (! isset($output['translated_text'], $output['detected_language_code'])) {
            $message->updateXfmExtraData([
                'translations' => [
                    $toLanguageCode => [
                        'error' => true,
                    ],
                ],
            ]);
            return;
        }

        $message->updateXfmExtraData([
            'translations' => [
                $toLanguageCode => [
                    'message' => $output['translated_text'],
                    'error'   => false,
                ],
            ],
        ]);

        if (! isset($message->xfm_extra_data['detected_language_code'])) {
            $message->updateXfmExtraData([
                'detected_language_code' => $output['detected_language_code'],
            ]);
        }
    }
}

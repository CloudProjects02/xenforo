<?php

namespace BS\RealTimeChat\Option;

class GPTModel
{
    public static function verifyOption(&$value, \XF\Entity\Option $option)
    {
        if (! self::isValidChatModel($value)) {
            $option->error(\XF::phrase('rtc_you_do_not_have_access_to_this_gpt_model'));
            return false;
        }

        return true;
    }

    public static function isValidChatModel(string $model): bool
    {
        $container = \XF::app()->container();

        if (! $container->offsetExists('chatGPT')) {
            return true;
        }

        /** @var \Orhanerday\OpenAi\OpenAi $api */
        $api = $container->offsetGet('chatGPT');
        $response = @json_decode($api->retrieveModel($model), true);
        return empty($response['error']);
    }
}

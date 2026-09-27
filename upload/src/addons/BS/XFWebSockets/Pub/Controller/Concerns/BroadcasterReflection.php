<?php

namespace BS\XFWebSockets\Pub\Controller\Concerns;

trait BroadcasterReflection
{
    protected function extractAuthParameters(string $pattern, string $channel)
    {
        $channelKeys = $this->extractChannelKeys($pattern, $channel);
        $channelKeys = array_filter($channelKeys, static function ($key) {
            return !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);
        return array_values($channelKeys);
    }

    protected function extractChannelKeys(string $pattern, string $channel): array
    {
        preg_match(
            '/^'.preg_replace('/\{(.*?)\}/',
                '(?<$1>[^\.]+)', $pattern).'/',
            $channel,
            $keys
        );

        return $keys;
    }

    protected function normalizeChannelName(string $channel)
    {
        foreach (['private-encrypted-', 'private-', 'presence-'] as $prefix) {
            if (mb_strpos($channel, $prefix) === 0) {
                return mb_substr($channel, mb_strlen($prefix));
            }
        }

        return $channel;
    }
}
<?php

namespace BS\XFWebSockets;

use BS\XFWebSockets\Broadcasting\Event;
use BS\XFWebSockets\Broadcasting\ForumChannel;
use BS\XFWebSockets\Broadcasting\UserChannel;
use BS\XFWebSockets\Exception\BroadcastException;
use Pusher\ApiErrorException;

class Broadcast
{
    public static function event(Event|string $shortName, ...$arguments): void
    {
        $event = $shortName instanceof Event
            ? $shortName
            : self::createEvent($shortName, $arguments);
        if (! $event) {
            return;
        }

        $name = method_exists($event, 'broadcastAs')
            ? $event->broadcastAs()
            : get_class($event);

        $channels = $event->toChannels();
        if (empty($channels)) {
            return;
        }

        $payload = self::getPayloadFromEvent($event);

        self::broadcast($channels, $name, $payload);
    }

    protected static function createEvent(string $shortName, $arguments): ?Event
    {
        $eventClass = \XF::stringToClass(
            $shortName,
            '%s\Broadcasting\Events\%s'
        );
        $eventClass = \XF::extendClass($eventClass);
        if (! class_exists($eventClass)) {
            return null;
        }

        $event = new $eventClass(...$arguments);
        if (! ($event instanceof Event)) {
            return null;
        }

        return $event;
    }

    protected static function getPayloadFromEvent(Event $event): array
    {
        $payload = $event->payload();
        return array_merge($payload, [
            'socket' => $event->socket ?? null
        ]);
    }

    public static function broadcast(
        array $channels,
        $event,
        array $payload = []
    ): void {
        $container = \XF::app()->container();

        $pusher = $container->offsetExists('pusher')
            ? $container->offsetGet('pusher')
            : null;
        if (! $pusher) {
            return;
        }

        $socket = $payload['socket'] ?? null;
        unset($payload['socket']);

        $parameters = $socket !== null ? ['socket_id' => $socket] : [];

        $channels = array_map(static fn($channel) => (string) $channel, $channels);

        try {
            foreach (array_chunk($channels, 100) as $channelsChunk) {
                $pusher->trigger($channelsChunk, $event, $payload, $parameters);
            }
        } catch (ApiErrorException $e) {
            \XF::logException(new BroadcastException(
                sprintf('Pusher error: %s.', $e->getMessage())
            ));
        }
    }

    public static function getChannelOptions(string $name): ?array
    {
        $channels = [
            ForumChannel::NAME_PATTERN => ForumChannel::class,
            UserChannel::NAME_PATTERN => UserChannel::class,
        ];

        \XF::app()->fire('broadcast_channels', [&$channels]);

        foreach ($channels as $pattern => $channel) {
            if (! self::channelNameMatchesPattern($name, $pattern)) {
                continue;
            }

            $channelClass = \XF::extendClass($channel);
            $channel = new $channelClass($name);

            return compact('pattern', 'channel');
        }

        return null;
    }

    protected static function channelNameMatchesPattern(
        string $channel,
        string $pattern
    ): bool {
        $pattern = str_replace('.', '\.', $pattern);

        return preg_match(
            '/^'.preg_replace('/\{(.*?)\}/', '([^\.]+)', $pattern).'$/',
            $channel
        );
    }

    public static function soketiConfigPath(): string
    {
        return \XF::getSourceDirectory().'/soketi.config.json';
    }
}

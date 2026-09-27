<?php

namespace BS\XFWebSockets;

use Pusher\Pusher;
use XF\App;
use XF\Container;
use XF\Template\Templater as XFTemplater;

class Listener
{
    public static function appSetup(App $app)
    {
        $options = $app->options();

        $port = $options->bsXFWebSocketsPusherPort;
        $id = $options->bsXFWebSocketsPusherAppID;
        $key = $options->bsXFWebSocketsPusherKey;
        $secret = $options->bsXFWebSocketsPusherSecret;
        $cluster = $options->bsXFWebSocketsPusherCluster;
        $host = $options->bsXFWebSocketsPusherHost;
        $scheme = parse_url($options->boardUrl, PHP_URL_SCHEME) ?: 'http';

        if (! $id || ! $key || ! $secret) {
            return;
        }

        if (file_exists($soketiConfigPath = Broadcast::soketiConfigPath())) {
            $host = '127.0.0.1';
            $soketiConfig = @json_decode(file_get_contents($soketiConfigPath), true);

            if (is_array($soketiConfig) && isset($soketiConfig['port'])) {
                $port = $soketiConfig['port'];
                $scheme = 'http';
            }
        }

        // If cluster is set, host and port are ignored
        // Using Pusher API defaults
        if (! empty($cluster)) {
            $host = null;
            $port = null;
            $scheme = null;
        }

        $app['pusher'] = static function (Container $c) use ($host, $port, $id, $key, $secret, $cluster, $scheme) {
            $options = [
                'host'      => $host,
                'port'      => $port,
                'scheme'    => $scheme,
                'encrypted' => true,
                'useTLS'    => false,
                'cluster'   => $cluster,
            ];
            $options = array_filter($options, static fn ($value) => $value !== null);

            return new Pusher($key, $secret, $id, $options);
        };
    }

    public static function templaterSetup(
        Container $container,
        XFTemplater $templater
    ): void {
        $ownTemplater = new (\XF::extendClass(Templater::class));
        $ownTemplater->setup($templater);
    }

    public static function helperJsGlobalPreRender(
        \XF\Template\Templater $templater,
        &$type,
        &$template,
        &$name,
        array &$arguments,
        array &$globalVars
    ) {
        $options = \XF::options();

        $host = $options->bsXFWebSocketsPusherHost ?: parse_url($options->boardUrl, PHP_URL_HOST);

        $echo = [
            'host' => $host,
            'cluster' => $options->bsXFWebSocketsPusherCluster,
            'key' => $options->bsXFWebSocketsPusherKey,
            'port' => $options->bsXFWebSocketsPusherPort,
        ];

        $globalVars['wsEcho'] = $echo;
    }
}

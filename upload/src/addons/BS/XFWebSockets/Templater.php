<?php

namespace BS\XFWebSockets;

use XF\Template\Templater as XFTemplater;

class Templater
{
    public function setup(XFTemplater $templater): void
    {
        $templater->addFunction('xfws_echo_config', [$this, 'config']);
    }

    public function config(XFTemplater $templater, &$escape)
    {
        $options = \XF::options();

        $host = $options->bsXFWebSocketsPusherHost
            ?: parse_url($options->boardUrl, PHP_URL_HOST);

        return [
            'host' => $host,
            'cluster' => $options->bsXFWebSocketsPusherCluster,
            'key' => $options->bsXFWebSocketsPusherKey,
            'port' => $options->bsXFWebSocketsPusherPort,
            'authEndpoint' => $templater->func('link', ['broadcasting/auth']),
            'userAuthEndpoint' => $templater->func(
                'link',
                ['broadcasting/user-auth']
            ),
            'csrfEndpoint' => $templater->func('link', ['broadcasting/refresh-csrf']),
            'pageUid' => $templater->func('unique_id'),
        ];
    }
}

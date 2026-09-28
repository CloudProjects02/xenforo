<?php

namespace CloudCheats\ProfileLayout;

class Listener
{
    public static function appPubSetup(\XF\Pub\App $app): void
    {
        $app->router()->addRoute('cc-scammer', [
            'default_format' => 'index',
            'prefix'         => 'cc-scammer',
            'controller'     => 'CloudCheats\\ProfileLayout\\Pub\\Controller\\Scammer',
        ]);
    }
}

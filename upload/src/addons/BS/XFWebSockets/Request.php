<?php

namespace BS\XFWebSockets;

class Request
{
    public static function getPageUid(): string
    {
        $request = \XF::app()->request();
        if (! $request) {
            return '';
        }

        return mb_substr($request->getServer('HTTP_X_WEBSOCKETS_PAGE_UID', ''), 0, 32);
    }
}
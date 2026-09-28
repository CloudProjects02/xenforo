<?php

namespace AL\Core\Listener;

use XF\Mvc\Reply\View;

class TemplaterGlobalData
{
    public static function listen(\XF\App $app, array &$data, $reply)
    {
        $mobileDetect = new \Mobile_Detect();

        $data['isMobileBrowser'] = $mobileDetect->isMobile();
        $data['isTabletBrowser'] = $mobileDetect->isTablet();
        $data['detectedUserAgent'] = $mobileDetect->getUserAgent();

        if ($reply instanceof View)
        {
            // Add reply params to the global data as we can use it in widget filters etc.
            $data['reply']['viewParams'] = $reply->getParams();
        }
    }
}


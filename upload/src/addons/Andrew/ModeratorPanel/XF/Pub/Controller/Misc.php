<?php

namespace Andrew\ModeratorPanel\XF\Pub\Controller;

use function count, strlen;

class Misc extends XFCP_Misc
{
    public function actionIpInfo()
    {
        if (!\XF::visitor()->canViewIps())
        {
            return $this->noPermission();
        }

        $ip = $this->filter('ip', 'str');

        if($this->options()->andrewModeratorPaneIPSearchIO)
        {
            $url = 'https://ipsearch.io/ip-lookup/?ip={ip}';
        }
        else
        {
            $url = $this->options()->ipInfoUrl;
        }

        if (strpos($url, '{ip}') === false)
        {
            $url = 'https://whatismyipaddress.com/ip/{ip}';
        }

        return $this->redirectPermanently(str_replace('{ip}', urlencode($ip), $url));
    }
}
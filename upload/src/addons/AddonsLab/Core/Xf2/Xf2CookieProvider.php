<?php

namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\CookieProviderInterface;

class Xf2CookieProvider implements CookieProviderInterface
{
    public function getCookie($name)
    {
        return \XF::app()->request()->getCookie($name);
    }

    public function setCookie($name, $value, $lifetime = 0, $httpOnly = false, $secure = null)
    {
        \XF::app()->response()->setCookie($name, $value, $lifetime, $secure, $httpOnly);
    }

    public function deleteCookie($name, $httpOnly = false, $secure = null)
    {
        $this->setCookie($name, false);
    }
}
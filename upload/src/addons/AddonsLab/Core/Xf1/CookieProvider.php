<?php

namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\CookieProviderInterface;

class CookieProvider implements CookieProviderInterface
{
    public function getCookie($name)
    {
        return \XenForo_Helper_Cookie::getCookie($name);
    }

    public function setCookie($name, $value, $lifetime = 0, $httpOnly = false, $secure = null)
    {
        return \XenForo_Helper_Cookie::setCookie($name, $value, $lifetime, $httpOnly, $secure);
    }

    public function deleteCookie($name, $httpOnly = false, $secure = null)
    {
        return \XenForo_Helper_Cookie::deleteCookie($name, $httpOnly, $secure);
    }
}
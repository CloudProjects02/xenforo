<?php
namespace AddonsLab\Core;

interface CookieProviderInterface
{
    public function getCookie($name);
    public function setCookie($name, $value, $lifetime = 0, $httpOnly = false, $secure = null);

    public function deleteCookie($name, $httpOnly = false, $secure = null);
}
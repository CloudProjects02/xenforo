<?php

namespace AddonsLab\Core\vB3;

use AddonsLab\Core\CookieProviderInterface;

class CookieProvider implements CookieProviderInterface
{
    /**
     * @var \vB_Input_Cleaner
     */
    protected $input;

    /**
     * CookieProvider constructor.
     */
    public function __construct()
    {
        $this->input = $GLOBALS['vbulletin']->input;
    }

    public function getCookie($name)
    {
        return $this->input->clean_gpc('c', COOKIE_PREFIX . $name);
    }

    public function setCookie($name, $value, $lifetime = 0, $httpOnly = false, $secure = null)
    {
        return vbsetcookie($name, $value, $lifetime === 0 ? false : true, $secure, $httpOnly);
    }

    public function deleteCookie($name, $httpOnly = false, $secure = null)
    {
        return $this->setCookie($name, false, 0, $httpOnly, $secure);
    }
}
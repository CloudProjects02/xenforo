<?php

namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\SessionProviderInterface;

class Xf2SessionProvider implements SessionProviderInterface
{
    public function getFromSession($name)
    {
        return \XF::session()->get($name);
    }

    public function saveToSession($name, $value)
    {
        \XF::session()->set($name, $value);
    }

    public function deleteFromSession($name)
    {
        \XF::session()->offsetUnset($name);
    }

    public function getSessionId()
    {
        return \XF::session()->getSessionId();
    }

}
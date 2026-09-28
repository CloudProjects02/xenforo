<?php

namespace AddonsLab\Core\Xf2;

use AddonsLab\Core\RegistryProviderInterface;

class Xf2RegistryProvider implements RegistryProviderInterface
{
    public function set($index, $value)
    {
        \XF::app()->container()->set($index, $value, false);
    }

    public function get($index)
    {
        return \XF::app()->container()->offsetGet($index);
    }

    public function isRegistered($index)
    {
        return \XF::app()->offsetExists($index);
    }
}

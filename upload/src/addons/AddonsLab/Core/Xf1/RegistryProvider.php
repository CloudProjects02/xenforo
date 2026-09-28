<?php
namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\RegistryProviderInterface;

class RegistryProvider implements RegistryProviderInterface
{
    public function set($index, $value)
    {
        \XenForo_Application::set($index, $value);
    }

    public function get($index)
    {
        return \XenForo_Application::get($index);
    }

    public function isRegistered($index)
    {
        return \XenForo_Application::isRegistered($index);
    }

}
<?php
namespace AddonsLab\Core\vB3;

use AddonsLab\Core\RegistryProviderInterface;

class RegistryProvider implements RegistryProviderInterface
{
    protected static $_registry;
    public function set($index, $value)
    {
        self::$_registry[$index]=$value;
    }

    public function get($index)
    {
        return self::$_registry[$index];
    }

    public function isRegistered($index)
    {
        return array_key_exists($index, self::$_registry);
    }
}
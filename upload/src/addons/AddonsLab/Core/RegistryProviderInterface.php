<?php
namespace AddonsLab\Core;

interface RegistryProviderInterface
{
    public function set($index, $value);
    
    public function get($index);
    
    public function isRegistered($index);
}
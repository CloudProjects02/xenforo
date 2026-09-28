<?php
namespace AddonsLab\Core;

interface SessionProviderInterface
{
    public function getFromSession($name);
    public function saveToSession($name, $value);
    public function deleteFromSession($name);
    public function getSessionId();
}
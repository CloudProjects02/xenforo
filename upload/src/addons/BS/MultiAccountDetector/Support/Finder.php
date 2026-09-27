<?php

namespace BS\MultiAccountDetector\Support;

class Finder
{
    public static function fetchColumn(\XF\Mvc\Entity\Finder $finder, string $key)
    {
        return array_map(
            static fn(array $value) => $value[$key],
            $finder->fetchColumns($key)
        );
    }
}
<?php

namespace BS\RealTimeChat\Utils;

class Date
{
    public static function getMicroTimestamp()
    {
        return (int)round(microtime(true) * 1000);
    }
}
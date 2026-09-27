<?php

namespace BS\RealTimeChat\Utils;

class RoomTag
{
    public const REGEX = '/^[A-Za-z0-9_]+\/[A-Za-z0-9_]+$/i';

    public const DEFAULT_TAG = 'r/Public';

    public static function matchRegex(string $tag)
    {
        return preg_match(self::REGEX, $tag);
    }

    public static function normalize(string $tag)
    {
        return str_replace('-', '/', $tag);
    }

    public static function urlEncode(string $tag)
    {
        return str_replace('/', '-', $tag);
    }

    public static function convertStrToTag(string $str)
    {
        $pattern = '/[^A-Za-z0-9_\/]+/';
        $replacement = '_';
        return preg_replace($pattern, $replacement, $str);
    }
}

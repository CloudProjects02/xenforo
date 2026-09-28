<?php

namespace CloudCheats\YearsOfService;

class Listener
{
    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater): void
    {
        // Register cc_yos($timestamp) template function.
        // Returns the number of complete years since the given Unix timestamp.
        $templater->addFunction('cc_yos', function ($templater, $escape, $timestamp)
        {
            return max(0, (int) floor((\XF::$time - (int) $timestamp) / (365.25 * 86400)));
        });
    }
}

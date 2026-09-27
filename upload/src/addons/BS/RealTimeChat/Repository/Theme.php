<?php

namespace BS\RealTimeChat\Repository;

use XF\Style;
use XF\Mvc\Entity\Repository;
use XF\Template\Templater;

class Theme extends Repository
{
    public function rebuildThemesCache()
    {
        $templater = clone $this->app()->templater();
        /** @var \XF\Style[] $styles */
        $styles = $this->app()->container('style.all');

        $cache = [];

        foreach ($styles as $style) {
            $cache[$style->getId()] = $this->buildThemesCache($templater, $style);
        }

        $this->app()->registry()->set('rtcThemes', $cache);

        return $cache;
    }

    protected function buildThemesCache(Templater $templater, Style $style)
    {
        $templater->setStyle($style);

        $themes = $templater->func('property', ['rtcThemes']);
        $iterationCount = count($themes);

        $cache = [];

        for ($i = 0; $i < $iterationCount; $i++) {
            $cache[$i] = $templater->func('rtc_room_theme', [$i, true]);
        }

        return $cache;
    }
}

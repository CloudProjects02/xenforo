<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 06/02/2021
 * Time: 23:40
 */

namespace XenConcept\HideBBCode\Listener;

class App
{
    protected static $_productId = 36;

    public static function appPubSetup(\XF\App $app)
    {
        $branding = $app->offsetExists('xenconcept_branding') ? $app->xenconcept_branding : [];

        $branding[] = self::$_productId;

        $app->xenconcept_branding = $branding;
    }
}
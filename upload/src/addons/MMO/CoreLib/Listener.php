<?php

namespace MMO\CoreLib;

class Listener
{
    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
    {
        /** @var \MMO\CoreLib\Template\TemplaterSetup $templaterSetup */
        $class = \XF::extendClass('MMO\CoreLib\Template\TemplaterSetup');
        $templaterSetup = new $class();

        $templater->addFunction('mcl_diff_for_human', [$templaterSetup, 'funcMclDiffForHuman']);
        $templater->addFunction('mcl_phrase_plural', [$templaterSetup, 'funcMclPhrasePlural']);
    }
}
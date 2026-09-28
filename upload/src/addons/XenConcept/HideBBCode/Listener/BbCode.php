<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\Listener;

class BbCode
{

    public static $addStripHideProcessor;

    public static function bbCodeRenderer(\XF\BbCode\Renderer\AbstractRenderer $renderer, $type)
    {
        if ($type == 'editorHtml' || $type == 'simpleHtml')
        {
            return;
        }

        $hideBbCodes = \XF::repository('XenConcept\HideBBCode:HideBbCode')->getHideBbCodes();

        foreach ($hideBbCodes AS $tag => $config)
        {
            $callback = str_replace('xc', 'renderTag', $config['name']);
            $callback = str_replace('Default', '', $callback);
            $renderer->addTag($tag, [
                'callback' => $callback,
                'trimAfter' => 2
            ]);
        }
    }

    public static function bbCodeRules(\XF\BbCode\RuleSet $ruleSet, $context, $subContext)
    {
        $hideBbCodes = \XF::repository('XenConcept\HideBBCode:HideBbCode')->getHideBbCodes();

        $hideMappedTo = str_replace('xc', '', strtolower(\XF::options()->xc_hide_bbcode_hideMappedTo));

        foreach ($hideBbCodes AS $tag => $config)
        {
            if ($tag == 'hide')
            {
                $config = $hideBbCodes[$hideMappedTo];
            }

            $ruleSet->addTag($tag, [
                'hasOption' => $config['hasOption'] == 'yes',
                'optionMatch' => $config['optionRegex']
            ]);
        }
    }

    /**
     * @param array $processorActionMap
     */
    public static function bbCodeProcessorActionMap(array &$processorActionMap)
    {
        $processorActionMap['hide'] = 'XenConcept\HideBBCode:StripHide';
    }

}
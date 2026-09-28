<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\Listener;

use XF;

class Templater
{

    public static function optionTabbed(\XF\Template\Templater $templater, &$type, &$template, &$name, array &$arguments, array &$globalVars)
    {
        if (isset($arguments['group']) && $arguments['group']->group_id == 'xc_hide_bbcode')
        {
            $name = 'simple_option_form_block';
            $template = 'xc_hide_bbcode_option_tabbed_macros';
        }
    }

    public static function templaterSetup(\XF\Container $container, \XF\Template\Templater &$templater)
    {
        $templater->addFunction('xc_hide_phrases', [__CLASS__, 'templaterFnHidePhrases']);
    }

    public static function templaterFnHidePhrases($templater, &$escape, $hidePhrase, $reactions = null)
    {
        $escape = false;

        $reactionsHtml  = [];

        foreach ($reactions AS $reactionId)
        {
            $reactionId = intval($reactionId);

            $reactionsHtml[$reactionId] = $templater->func('reaction', [
                [
                    'id' => $reactionId,
                    'showtitle' => true,
                    'small' => true,
                    'hasreaction' => true
                ]
            ], $escape);
        }

        return $hidePhrase . ' ' . implode(', ', $reactionsHtml);
    }

    public static function editor(\XF\Template\Templater $templater, &$type, &$template, array &$params)
    {
        /** @var \XenConcept\HideBBCode\Repository\HideBbCode $hideBbCodeRepo */
        $hideBbCodeRepo = \XF::repository('XenConcept\HideBBCode:HideBbCode');

        if (!$hideBbCodeRepo->canUseHideBbCodes())
        {
            $params['removeButtons'][] = '_xcHide';
            return;
        }

        $allowedHideButtons = $hideBbCodeRepo->getAllowedHideButtons();

        $xcHideIcons = [];
        foreach ($hideBbCodeRepo->getHideBbCodes() as $k => $hide)
        {
            if (in_array($hide['name'], $allowedHideButtons))
            {
                $xcHideIcons[$k] = [
                    'name' => $hide['name'],
                    'title' => \XF::phrase('xc_hide_bbcode_hide_bb_code_title.' . $k),
                    'type' => 'fa',
                    'value' => 'eye',
                    'option' => $hide['hasOption']
                ];
            }
        }

        if (empty($xcHideIcons))
        {
            $params['removeButtons'][] = '_xcHide';
            return;
        }

        $params['xcHideIcons'] = $xcHideIcons;
    }
}
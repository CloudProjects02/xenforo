<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\Help;

use XF\Mvc\Controller;
use XF\Mvc\Reply\View;

class HideBbCodes
{
    public static function renderHideBbCodes(Controller $controller, View &$response)
    {
        /** @var \XenConcept\HideBBCode\Repository\HideBbCode $hideBbCodeRepo */
        $hideBbCodes = $controller->repository('XenConcept\HideBBCode:HideBbCode')->getHideBbCodesForHelpPage();

        $hideMappedTo = strtolower(str_replace('xc', '', \XF::options()->xc_hide_bbcode_hideMappedTo));
        $hideMapped = $hideBbCodes[$hideMappedTo];


        $hideBbCodes['hide']['hasOption']   = $hideMapped['hasOption'];
        $hideBbCodes['hide']['optionRegex'] = $hideMapped['optionRegex'];


        $viewParams = [
            'hideBbCodes' => $hideBbCodes
        ];
        $response->setParams($viewParams);
    }
}
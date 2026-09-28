<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2018
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\Str;

use XenConcept\HideBBCode\Listener\BbCode;

class Formatter extends XFCP_Formatter
{

    public function getBbCodeForQuote($bbCode, $context)
    {
        BbCode::$addStripHideProcessor = true;

        try
        {
            return parent::getBbCodeForQuote($bbCode, $context);
        }
        finally
        {
            BbCode::$addStripHideProcessor = null;
        }
    }

    public function snippetString($string, $maxLength = 0, array $options = [])
    {
        $string = preg_replace("#\[(HIDE|HIDEREPLY|HIDEPOSTS|HIDEREACT|HIDEREPLYREACT|HIDESHOWTOGROUPS|HIDEUSER)[^\]]*\].*\[/\\1\]#siU", \XF::phrase('xc_hide_bbcode_hidden_content'), $string);

        return parent::snippetString($string, $maxLength, $options);
    }

}
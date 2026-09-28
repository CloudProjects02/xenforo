<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\BbCode\Renderer;


use XenConcept\HideBBCode\BbCode\HideBbCodeTrait;

class EmailHtml extends XFCP_EmailHtml
{
    use HideBbCodeTrait;

    protected function getRenderedHide($tag, $content, $visibility)
    {
        $renderParams = [
            'hideTag' => $tag,
            'content' => $content,
            'visibility' => $visibility
        ];

        return $this->templater->renderTemplate('email:xc_hide_bbcode_bb_code_tag_hide', $renderParams);
    }

}
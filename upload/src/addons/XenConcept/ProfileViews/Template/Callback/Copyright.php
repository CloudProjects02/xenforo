<?php
/*************************************************************************
 * Profile Views - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\Template\Callback;

class Copyright
{
    /**
     * @return string
     */
    public static function getCopyrightText()
    {
        $app = \XF::app();

        $branding = $app->offsetExists('xenconcept_branding') ? $app->xenconcept_branding : [];

        if (!count($branding) OR !is_array($branding))
        {
            return '';
        }

        $html = '<div>
			Some of the add-ons on this site are powered by  <a class="u-concealed" rel="nofollow noopener" href="https://www.xen-concept.com/products" target="_blank">XenConcept&#8482;</a>
			&copy;2017-' . date('Y') . ' <a class="u-concealed" rel="nofollow noopener" href="https://www.xen-concept.com" target="_blank">XenConcept Ltd. (<a class="u-concealed" rel="nofollow noopener" href="https://www.xen-concept.com/products/?products=' . implode(',', $branding) .'" target="_blank">Details</a>)</a>
		</div>';

        $app->xenconcept_branding = [];

        return $html;
    }
}
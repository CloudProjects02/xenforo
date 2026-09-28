<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework\Service;

use XF\Service\AbstractService;
use XF\Template\Templater;

class ActiveFilterHelper extends AbstractService
{
    public function replaceSubFilter(Templater $templater, $value, &$escape, $key, $from, $to = null, $subItemId = null)
    {
        if (!isset($value[$key]) && empty($to))
        {
            return $value;
        }

        // Make sure the key exists
        $value[$key] ??= [];

        if (empty($value[$key]) && empty($to))
        {
            unset($value[$key]);

            return $value;
        }

        if ($subItemId && !empty($value[$key][$from]) && is_array($value[$key][$from]))
        {
            $subItemIndex = array_search($subItemId, $value[$key][$from], false);
            if ($subItemIndex !== false)
            {
                $value[$key][$from] = $templater->filterReplace($templater, $value[$key][$from], $escape, $subItemIndex, $to);
                $value[$key][$from] = array_filter($value[$key][$from], function ($item) {
                    return $item !== null;
                });
            }

            if (empty($value[$key][$from]))
            {
                unset($value[$key][$from]);
            }
        }
        else
        {
            $value[$key] = $templater->filterReplace($templater, $value[$key], $escape, $from, $to);
        }

        $value[$key] = array_filter($value[$key], function ($item) {
            return $item !== null;
        });

        if (isset($value[$key]['__config'][$from]))
        {
            unset($value[$key]['__config'][$from]);
        }
        if (empty($value[$key]['__config']))
        {
            unset($value[$key]['__config']);
        }

        if (empty($value[$key]))
        {
            unset($value[$key]);
        }

        return $value;
    }
}

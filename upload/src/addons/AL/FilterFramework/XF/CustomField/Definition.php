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


namespace AL\FilterFramework\XF\CustomField;

use XF\CustomField\Set;

class Definition extends XFCP_Definition
{
    public function getFieldChoicesForFilterableList(Set $set)
    {
        $choices = $this->field_choices;

        if (count($choices) > 100)
        {
            $value = $set[$this->field_id] ?? [];

            if (!is_array($value))
            {
                // Normalize to array to work for both single and multiple selections
                $value = [$value];
            }

            // Filter by keys
            return array_filter($choices, function ($key) use ($value) {
                return in_array($key, $value);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $choices;
    }

    public function trimHtmlFromDisplayTemplate()
    {
        $this->field['display_template'] = strip_tags($this->field['display_template']);
    }
}

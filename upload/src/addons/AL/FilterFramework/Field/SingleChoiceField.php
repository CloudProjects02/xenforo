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


namespace AL\FilterFramework\Field;

class SingleChoiceField extends AbstractField
{
    protected $exact_match = true;

    protected $choices = [];

    public function __construct(array $choices)
    {
        $this->choices = array_values($choices);
    }

    public function getAsString()
    {
        return implode(',', $this->choices);
    }

    public function getFilterOrder()
    {
        return 5;
    }

    public function getMetadataMatchConditions($metadataColumn)
    {
        $conditions = [];
        foreach ($this->choices as $matchValue)
        {
            if ($this->exact_match)
            {
                // the option value should match exactly
                $conditions[] = "{$metadataColumn} REGEXP '(^| )_md_field_choice_single_" . \XF::db()->escapeString($matchValue) . "($| )'";
            }
            else
            {
                // option value will also match any other options that start with the same value
                $conditions[] = "{$metadataColumn} LIKE '%_md_field_choice_single_" . \XF::db()->escapeString($matchValue) . "%'";
            }
        }

        return $conditions;
    }

    /**
     * @return array
     */
    public function getChoices()
    {
        return $this->choices;
    }

    public function setExactMatch($exact_match)
    {
        $this->exact_match = $exact_match;
    }

    public function isExactMatch()
    {
        return $this->exact_match;
    }
}

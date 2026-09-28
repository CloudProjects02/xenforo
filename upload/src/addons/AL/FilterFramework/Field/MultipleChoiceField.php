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

use AL\FilterFramework\Service\TypeProvider;

class MultipleChoiceField extends AbstractField
{
    protected $choices = [];

    public function __construct(array $choices)
    {
        $this->choices = $choices;
    }

    public function getAsString()
    {
        return implode(',', $this->choices);
    }

    public function getFilterOrder()
    {
        return 40;
    }

    public function getMetadataMatchConditions($metadataColumn)
    {
        $conditions = [];
        foreach ($this->choices as $matchValue)
        {
            $conditions[] = "{$metadataColumn} LIKE '%" . TypeProvider::$choiceSeparator . \XF::db()->escapeString($matchValue) . TypeProvider::$choiceSeparator . "%'";
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
}

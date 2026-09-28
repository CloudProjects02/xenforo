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

use AL\FilterFramework\Entity\FieldData;
use XF\Entity\AbstractField;
use XF\Service\AbstractService;

class FieldCreator extends AbstractService
{
    public function setupFieldEntity($field_id, $fieldDef, $fieldShortName)
    {
        /** @var AbstractField $field */
        $field = \XF::finder($fieldShortName)->whereId($field_id)->fetchOne();
        if ($field === null)
        {
            $field = \XF::em()->create($fieldShortName);
            $field->field_id = $field_id;
        }

        $field->field_type = $fieldDef['field_type'];

        if (isset($fieldDef['match_type']))
        {
            $field->match_type = $fieldDef['match_type'];
        }
        if (isset($fieldDef['match_params']))
        {
            $field->match_params = $fieldDef['match_params'];
        }
        if (isset($fieldDef['field_choices']))
        {
            $field->field_choices = $fieldDef['field_choices'];
        }

        /** @var FieldData $fieldData */
        $fieldData = $field->getRelationOrDefault('FieldData');
        $fieldData->allow_filter = 1;
        $fieldData->allow_search = 1;
        $fieldData->allow_sorting = 1;

        $fieldData->bulkSet($fieldDef['field_data'] ?? []);

        return $field;
    }
}

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


namespace AL\FilterFramework\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS.
 *
 * @property string content_type
 * @property string field_id
 * @property string filter_template
 * @property bool allow_filter
 * @property bool allow_search
 * @property bool allow_sorting
 * @property int display_options
 * @property string default_match_type
 */
class FieldData extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_alff_field_data';
        $structure->shortName = 'AL\FilterFramework:FieldData';
        $structure->primaryKey = ['content_type', 'field_id'];
        $structure->columns = [
            'content_type' => ['type' => self::STR],
            'field_id' => ['type' => self::STR],
            'filter_template' => ['type' => self::STR, 'default' => 'checkbox', 'allowedValues' => ['', 'select', 'radio', 'checkbox', 'multiselect']],
            'default_match_type' => ['type' => self::STR, 'default' => 'OR', 'allowedValues' => ['OR', 'AND']],
            'allow_filter' => ['type' => self::BOOL, 'default' => 0],
            'allow_search' => ['type' => self::BOOL, 'default' => 0],
            'allow_sorting' => ['type' => self::BOOL, 'default' => 0],
            'display_options' => ['type' => self::BOOL, 'default' => 0],
        ];

        $structure->getters = [
            'is_sortable' => true,
        ];
        $structure->relations = [];

        return $structure;
    }

    public function get($key)
    {
        return parent::get($key);
    }

    public function toArray($allowGetters = true)
    {
        return parent::toArray($allowGetters);
    }
}

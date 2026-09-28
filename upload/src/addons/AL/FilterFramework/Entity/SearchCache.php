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
 * @property string search_hash
 * @property string result_list
 * @property string field_list Comma-separated list of field IDs involved in the cache. Used for cache invalidation.
 * @property int creation_date
 */
class SearchCache extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_alff_search_cache';
        $structure->shortName = 'AL\FilterFramework:SearchCache';
        $structure->primaryKey = ['content_type', 'search_hash'];
        $structure->columns = [
            'content_type' => ['type' => self::STR],
            'search_hash' => ['type' => self::STR],
            'result_list' => ['type' => self::STR],
            'node_list' => ['type' => self::STR],
            'field_list' => ['type' => self::STR],
            'creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
        ];

        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}

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
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

/**
 * This is a read-only entity, just used to generate the joins
 * COLUMNS.
 *
 * @property string content_type
 * @property int content_id
 * @property string message
 * @property string metadata
 */
class SearchIndex extends Entity
{
    public function __construct(Manager $em, Structure $structure, array $values = [], array $relations = [])
    {
        parent::__construct($em, $structure, $values, $relations);
        $this->setReadOnly(true);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_search_index';
        $structure->shortName = 'AL\FilterFramework:SearchIndex';
        $structure->primaryKey = ['content_type', 'content_id'];
        $structure->columns = [
            'content_type' => ['type' => self::STR, 'maxLength' => 25, 'match' => 'alphanumeric'],
            'content_id' => ['type' => self::INT, 'default' => 0],
            'title' => ['type' => self::STR, 'default' => ''],
            'message' => ['type' => self::STR, 'default' => ''],
            'metadata' => ['type' => self::STR, 'default' => ''],
        ];

        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}

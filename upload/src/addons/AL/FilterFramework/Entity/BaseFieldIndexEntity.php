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

use AL\FilterFramework\FilterApp;
use XF\Entity\AbstractField;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Base field index entity implementation. Every field type should extend this entity and provide the required information
 * COLUMNS.
 *
 * @property int field_index_id
 * @property int content_id
 * @property string content_type
 * @property int modified_date
 * @property string field_id
 * @property string field_value
 * @property int field_int
 * @property float field_float
 * @property float field_color_l
 * @property float field_color_a
 * @property float field_color_b
 * @property string field_location_country_code
 * @property float field_location_state_id
 * @property float field_location_city_id
 * @property string field_location_zip_code
 * @property float field_location_street_address
 * @property string field_location_full_address
 * @property float field_location_lat
 * @property float field_location_lng
 * RELATIONS
 * @property Entity ContentEntity
 * @property AbstractField FieldEntity
 */
abstract class BaseFieldIndexEntity extends Entity implements FieldIndexEntityInterface
{
    public function __get($key)
    {
        $value = parent::__get($key);

        if ($key === 'field_value' && $this->field_id)
        {
            $value = FilterApp::getContextProvider(static::getContentTypeProvider())->fixFieldValueType($this->field_id, $value);
        }

        return $value;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_alff_field_index';
        $structure->shortName = static::getContentTypeProvider()->getIndexEntityName();
        $structure->primaryKey = 'field_index_id';
        $structure->contentType = static::getContentTypeProvider()->getIndexContentType();
        $structure->columns = [
            'field_index_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'content_id' => ['type' => self::UINT, 'required' => true],
            'content_type' => ['type' => self::STR, 'required' => true],
            'field_id' => [
                'type' => self::STR, 'maxLength' => 25,
                'match' => 'alphanumeric',
            ],
            'modified_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'field_value' => ['type' => self::STR, 'default' => ''],
            'field_int' => ['type' => self::INT, 'default' => 0],
            'field_float' => ['type' => self::FLOAT, 'default' => 0],
            'field_color_l' => ['type' => self::FLOAT, 'default' => 0],
            'field_color_a' => ['type' => self::FLOAT, 'default' => 0],
            'field_color_b' => ['type' => self::FLOAT, 'default' => 0],

            'field_location_country_code' => ['type' => self::STR, 'default' => ''],
            'field_location_state_id' => ['type' => self::FLOAT, 'default' => 0],
            'field_location_city_id' => ['type' => self::FLOAT, 'default' => 0],
            'field_location_zip_code' => ['type' => self::STR, 'default' => ''],
            'field_location_street_address' => ['type' => self::STR, 'default' => ''],
            'field_location_full_address' => ['type' => self::STR, 'default' => ''],
            'field_location_lat' => ['type' => self::FLOAT, 'default' => 0, 'nullable' => true],
            'field_location_lng' => ['type' => self::FLOAT, 'default' => 0, 'nullable' => true],
        ];

        $structure->getters = [];
        $structure->relations = [
            'FieldEntity' => [
                'entity' => static::getContentTypeProvider()->getFieldEntityName(),
                'type' => self::TO_ONE,
                'conditions' => [
                    [static::getContentTypeProvider()->getFieldEntityPrimaryKeyName(), '=', '$field_id'],
                    /* ['$content_type', '=', static::_getEntityContentType()], */ // content type filter will be applied on entire query by extended finder
                ],
                'primary' => true,
            ],
            'ContentEntity' => [
                'entity' => static::getContentTypeProvider()->getContentEntityName(),
                'type' => self::TO_ONE,
                'conditions' => [
                    [static::getContentTypeProvider()->getContentPrimaryKeyName(), '=', '$content_id'],
                    /* ['$content_type', '=', static::_getEntityContentType()], */ // see content type filter note above
                ],
                'primary' => true,
            ],
            'SearchIndex' => [
                'entity' => 'AL\FilterFramework:SearchIndex',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['content_id', '=', '$field_index_id'],
                    ['content_type', '=', static::getContentTypeProvider()->getIndexContentType()],
                ],
                'primary' => true,
            ],
        ];

        $structure->behaviors['XF:Indexable'] = [
            'checkForUpdates' => ['modified_date', 'field_value'],
        ];

        return $structure;
    }

    public function canView(&$error = null)
    {
        // fields are not directly viewed and their view permissions are controlled by their container
        return true;
    }

    protected function _preSave()
    {
        parent::_preSave();

        if ($this->isChanged('field_value'))
        {
            $typeProvider = FilterApp::getTypeProvider();

            $this->modified_date = \XF::$time;

            /** @var AbstractField $field */
            $field = $this->getRelation('FieldEntity');

            if (!$field)
            {
                return;
            }

            $fullMetaData = FilterApp::getContextProvider(static::getContentTypeProvider())->getMetaData($this);
            $defaultMetaData = FilterApp::getFieldIndexer(static::getContentTypeProvider())->getSqlIndexDefaultMetaData();

            $metaData = array_filter(
                $fullMetaData,
                static function ($key) use ($defaultMetaData) {
                    return array_key_exists($key, $defaultMetaData);
                },
                ARRAY_FILTER_USE_KEY
            );

            $this->bulkSet($metaData);
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        $field_id = $this->field_id;

        $cache = \XF::finder('AL\FilterFramework:SearchCache')->whereSql('content_type=%s AND %s IN (field_list)', static::getContentTypeProvider()->getContentType(), $field_id)->fetch();
        foreach ($cache as $item)
        {
            try
            {
                $item->delete();
            }
            catch (\Exception $exception)
            {
                // can be lock timeout, just skip deleting the cache
            }
        }
    }
}

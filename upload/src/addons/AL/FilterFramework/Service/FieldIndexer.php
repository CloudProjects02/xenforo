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

use AL\FilterFramework\ContentTypeProviderInterface;
use AL\FilterFramework\Entity\BaseFieldIndexEntity;
use AL\FilterFramework\FilterApp;
use XF\App;
use XF\Behavior\CustomFieldsHolder;
use XF\CustomField\Definition;
use XF\CustomField\Set;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Str\Formatter;

/**
 * Class FieldIndexer.
 */
class FieldIndexer extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);
        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function getSqlIndexDefaultMetaData()
    {
        return [
            'field_int' => 0,
            'field_float' => 0,
            'field_color_l' => 0,
            'field_color_a' => 0,
            'field_color_b' => 0,
            'field_location_country_code' => '',
            'field_location_state_id' => 0,
            'field_location_city_id' => 0,
            'field_location_zip_code' => '',
            'field_location_street_address' => '',
            'field_location_full_address' => '',
            'field_location_lat' => null,
            'field_location_lng' => null,
        ];
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function getFormattedValueForColumn(Definition $definition, $value)
    {
        if ($value === '' || $value === null)
        {
            return '';
        }

        switch ($definition->type_group)
        {
            case 'rich_text':
            case 'text':
                $value = (new Formatter())->wholeWordTrim($value, 200);

                break;
        }

        return $definition->getFormattedValue($value);
    }

    /**
     * @param Entity $parentEntity
     *                             Updates the index of custom fields with updates values from the entity
     *                             only if values are changed
     *
     * @throws \Exception
     * @throws PrintableException
     */
    public function updateEntityFields(Entity $parentEntity)
    {
        /** @var CustomFieldsHolder $behavior */
        $behavior = $parentEntity->getBehavior('XF:CustomFieldsHolder');
        $column = $behavior->getConfig('column');

        if ($parentEntity->isChanged($column))
        {
            $newSet = $parentEntity->getValue($column);
            $oldSet = $parentEntity->isUpdate() ? $parentEntity->getExistingValue($column) : [];

            if ($parentEntity->isInsert() && !$newSet)
            {
                // nothing to do
                return;
            }

            $newSet = $this->filterEmptyNumericValues($newSet);

            $removedKeys = [];
            $replacements = [];

            foreach ($oldSet as $key => $oldValue)
            {
                if (!isset($newSet[$key]))
                {
                    $removedKeys[] = $key;
                }
                else
                {
                    $newValue = $newSet[$key];
                    if ($oldValue !== $newValue)
                    {
                        // updated value
                        $replacements[$key] = $newValue;
                    }
                }
            }

            foreach ($newSet as $key => $newValue)
            {
                if (isset($oldSet[$key]))
                {
                    // handled above
                    continue;
                }

                // new value
                $replacements[$key] = $newValue;
            }

            if ($removedKeys)
            {
                // delete the data from the index
                $fields = $this->getFinder($parentEntity)->where('field_id', $removedKeys)->fetch();
                foreach ($fields as $field)
                {
                    $field->delete();
                }
            }

            if ($replacements)
            {
                foreach ($replacements as $key => $value)
                {
                    /** @var BaseFieldIndexEntity $field */
                    $field = $this->getFinder($parentEntity)->where('field_id', $key)->fetchOne();
                    if ($field === null)
                    {
                        $field = $this->getIndexEntity($parentEntity);
                        $field->set('field_id', $key);
                    }

                    $field->field_value = is_array($value) ? serialize($value) : $value;
                    $field->save();
                }
            }
        }
    }

    public function filterEmptyNumericValues(array $customFields)
    {
        $typeProvider = FilterApp::getTypeProvider();
        $fields = $this->contentTypeProvider->getFieldDefinitions();
        foreach ($customFields as $fieldId => $fieldValue)
        {
            if (!isset($fields[$fieldId]))
            {
                continue;
            }
            $field = $fields[$fieldId];

            if (
                ($typeProvider->isInteger($field) || $typeProvider->isFloat($field))
                && $fieldValue === ''
            ) {
                unset($customFields[$fieldId]);

                continue;
            }
        }

        return $customFields;
    }

    public function deleteEntityFields(Entity $parentEntity)
    {
        $fields = $this->getFinder($parentEntity)->fetch();

        foreach ($fields as $field)
        {
            $field->delete();
        }
    }

    /**
     * Delete the index not having any fields associated with.
     */
    public function deleteOrphanedIndex()
    {
        // get all available fields
        $fields = $this->contentTypeProvider->getFieldDefinitions();

        $fieldIds = array_column($fields, 'field_id');

        if (empty($fieldIds))
        {
            $fieldIds[] = '';
        }

        $fieldIds = array_map(function ($fieldId) {
            return '\'' . $fieldId . '\'';
        }, $fieldIds);

        // get all field IDs from xf_alff_field_index which are not in the list of current field IDs
        $orphanFieldIds = \XF::db()->fetchAllColumn('
            SELECT DISTINCT  field_id FROM xf_alff_field_index
            WHERE field_id NOT IN (' . implode(',', $fieldIds) . ')
        ');

        foreach ($orphanFieldIds as $fieldId)
        {
            $this->deleteFieldIndex($fieldId);
        }

        return $fieldIds;
    }

    /**
     * @param string $fieldId
     *                        Deletes field from the index and its additional data from xf_alff_field_data field
     */
    public function deleteFieldIndex($fieldId)
    {
        $fieldData = \XF::finder('AL\FilterFramework:FieldData')
            ->where('content_type', $this->contentTypeProvider->getContentType())
            ->where('field_id', $fieldId)
            ->fetchOne();

        if ($fieldData)
        {
            $fieldData->delete();
        }

        $db = \XF::db();

        // fetch the IDs of all indexed items
        $fieldIndexArray = $db->fetchAllColumn('
            SELECT field_index_id FROM xf_alff_field_index
            WHERE content_type=?
            AND field_id=?
        ', [
            $this->contentTypeProvider->getContentType(),
            $fieldId,
        ]);

        if ($fieldIndexArray)
        {
            // delete the data from the index
            $db->delete('xf_alff_field_index', 'content_type=? AND field_id=?', [
                $this->contentTypeProvider->getContentType(),
                $fieldId,
            ]);

            // delete indexed data from XenForo index
            \XF::app()->search()->delete($this->contentTypeProvider->getIndexContentType(), $fieldIndexArray);
        }
    }

    /**
     * @param Entity $parentEntity
     *                             Re-index all custom fields of an entity by deleting all existing ones and re-creating them
     */
    public function reIndexEntityFields(Entity $parentEntity)
    {
        // delete the existing fields first
        $this->deleteEntityFields($parentEntity);

        $allFields = $this->contentTypeProvider->getFieldDefinitions();

        // find all fields and delete them from our index
        $indexName = $this->contentTypeProvider->getIndexEntityName();

        $contentId = $parentEntity->{$this->contentTypeProvider->getContentPrimaryKeyName()};
        $contentType = $this->contentTypeProvider->getContentType();

        /** @var CustomFieldsHolder $behavior */
        $behavior = $parentEntity->getBehavior('XF:CustomFieldsHolder');
        $column = $behavior->getConfig('column');

        /** @var Set $originalValue */
        $originalValue = $parentEntity->get($column);

        $fieldValues = $originalValue->getFieldValues();

        $fieldValues = $this->filterEmptyNumericValues($fieldValues);

        // loop in existing values and create field index entry
        foreach ($fieldValues as $fieldId => $fieldValue)
        {
            if (!isset($allFields[$fieldId]))
            {
                // field value exists in the cache, but deleted later
                continue;
            }

            // fix the stored value if field type has changed
            $fieldValue = FilterApp::getContextProvider($this->contentTypeProvider)->fixFieldValueType($fieldId, $fieldValue);

            /** @var BaseFieldIndexEntity $field */
            $field = \XF::em()->create($indexName);
            $field->set('content_id', $contentId);
            $field->set('content_type', $contentType);
            $field->set('field_id', $fieldId);
            $field->field_value = is_array($fieldValue) ? serialize($fieldValue) : $fieldValue;
            $field->save();
        }
    }

    public function getIndexEntity(Entity $parentEntity)
    {
        $indexName = $this->contentTypeProvider->getIndexEntityName();
        $contentId = $parentEntity->{$this->contentTypeProvider->getContentPrimaryKeyName()};
        $contentType = $this->contentTypeProvider->getContentType();

        /** @var BaseFieldIndexEntity $indexEntity */
        $indexEntity = $this->em()->create($indexName);
        $indexEntity->content_type = $contentType;
        $indexEntity->content_id = $contentId;

        return $indexEntity;
    }

    /**
     * @return Finder
     *                Creates a finder with pre-setup conditions for content id and content type
     */
    public function getFinder(Entity $parentEntity)
    {
        $indexName = $this->contentTypeProvider->getIndexEntityName();

        $contentId = $parentEntity->getEntityId();
        $contentType = $this->contentTypeProvider->getContentType();

        return \XF::finder($indexName)
            ->where('content_id', $contentId)
            ->where('content_type', $contentType);
    }
}

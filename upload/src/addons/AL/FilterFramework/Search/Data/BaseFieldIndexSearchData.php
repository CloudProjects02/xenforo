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


namespace AL\FilterFramework\Search\Data;

use AL\FilterFramework\ContentTypeProviderInterface;
use AL\FilterFramework\Entity\BaseFieldIndexEntity;
use AL\FilterFramework\FilterApp;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

/**
 * Class BaseFieldIndexSearchData.
 */
abstract class BaseFieldIndexSearchData extends AbstractData
{
    /**
     * @param null $error
     *
     * @return bool
     *              This information is not directly viewable in search results, so no one can view this entities
     */
    public function canViewContent(Entity $entity, &$error = null)
    {
        return false;
    }

    public function getEntityWith($forView = false)
    {
        return $this->_getContentTypeProvider()->getEntityWith($forView);
    }

    /**
     * @param Entity|BaseFieldIndexEntity $entity
     *
     * @return bool|IndexRecord
     *
     * @throws PrintableException
     */
    public function getIndexData(Entity $entity)
    {
        if (
            !$entity->FieldEntity
            || !$entity->ContentEntity
        ) {
            return false;
        }

        /** @var BaseFieldIndexEntity $entity */
        // normalization should be applied already in the getter, converting string to array and fixing the type if needed
        $fieldValue = $entity->field_value;

        $content = FilterApp::getTypeProvider()->getText($entity->FieldEntity->toArray(), $fieldValue);

        $contentUserId = $this->_getContentTypeProvider()->getContentUserId($entity);
        $contentDiscussionId = $this->_getContentTypeProvider()->getContentDiscussionId($entity);

        return IndexRecord::create($this->_getContentTypeProvider()->getIndexContentType(), $entity->field_index_id, [
            'title' => '',
            'message' => $content,
            'date' => $entity->modified_date,
            'user_id' => $contentUserId,
            'discussion_id' => $contentDiscussionId,
            'metadata' => $this->getMetaData($entity),
        ]);
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('field_id', MetadataStructure::KEYWORD);

        // imploded values of all selections (values will be separated by is __0__)
        $structure->addField('field_choice_multiple', MetadataStructure::KEYWORD);

        // single selection value
        $structure->addField('field_choice_single', MetadataStructure::KEYWORD);

        // numeric int values, used to store the dates as well
        $structure->addField('field_int', MetadataStructure::INT);

        // numeric float values
        $structure->addField('field_float', MetadataStructure::FLOAT);

        // colors will be stored in 3 separate properties in Lab format
        $structure->addField('field_color_l', MetadataStructure::FLOAT);
        $structure->addField('field_color_a', MetadataStructure::FLOAT);
        $structure->addField('field_color_b', MetadataStructure::FLOAT);

        // location will be stored in separate fields for each its component
        $structure->addField('field_location_country_code', MetadataStructure::KEYWORD);
        $structure->addField('field_location_state_id', MetadataStructure::FLOAT);
        $structure->addField('field_location_city_id', MetadataStructure::FLOAT);
        $structure->addField('field_location_zip_code', MetadataStructure::KEYWORD);
        $structure->addField('field_location_street_address', MetadataStructure::KEYWORD);
        $structure->addField('field_location_full_address', MetadataStructure::KEYWORD);
        $structure->addField('field_location_lat', MetadataStructure::FLOAT);
        $structure->addField('field_location_lng', MetadataStructure::FLOAT);

        $this->_getContentTypeProvider()->setupMetadataStructure($structure);
    }

    /**
     * @param Entity|BaseFieldIndexEntity $entity
     *
     * @return mixed|null
     */
    public function getResultDate(Entity $entity)
    {
        return $entity->modified_date;
    }

    public function getTemplateData(Entity $entity, array $options = [])
    {
        return [
            'field' => $entity,
            'options' => $options,
        ];
    }

    /**
     * @return ContentTypeProviderInterface
     */
    abstract protected function _getContentTypeProvider();

    protected function getMetaData(BaseFieldIndexEntity $entity)
    {
        return FilterApp::getContextProvider($this->_getContentTypeProvider())->getMetaData($entity);
    }
}

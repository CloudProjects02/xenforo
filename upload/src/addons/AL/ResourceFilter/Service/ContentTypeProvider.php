<?php
/** 
* @package [AddonsLab] Resource Filter
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 3.6.1
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


namespace AL\ResourceFilter\Service;

use AL\FilterFramework\AbstractContentTypeProvider;
use AL\FilterFramework\Entity\BaseFieldIndexEntity;
use AL\FilterFramework\FilterApp;
use AL\ResourceFilter\App;
use AL\ResourceFilter\Entity\ResourceFieldIndex;
use AL\ResourceFilter\XF\Search\Search;
use AL\ResourceFilter\XFRM\Entity\Category;
use XF\Mvc\Controller;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceItem;

class ContentTypeProvider extends AbstractContentTypeProvider
{
    public function getContentEntityName()
    {
        return 'XFRM:ResourceItem';
    }

    public function getCategoryEntityName()
    {
        return 'XFRM:Category';
    }

    public function getContentType()
    {
        return 'resource';
    }

    public function getContentPrimaryKeyName()
    {
        return 'resource_id';
    }

    public function getCategoryContentType()
    {
        return 'resource_category';
    }

    public function getIndexContentType()
    {
        return 'resource_field';
    }

    public function getFieldEntityName()
    {
        return 'XFRM:ResourceField';
    }

    public function getIndexEntityName()
    {
        return 'AL\ResourceFilter:ResourceFieldIndex';
    }


    public function getColorSimilarityIndex()
    {
        return App::getOptionProvider()->getOption('alrf_color_similarity_index');
    }

    public function getPhraseForOption($field, $option)
    {
        return 'xfrm_resource_field_choice.' . $field['field_id'] . '_' . $option;
    }

    public function getFieldEntityPrimaryKeyName()
    {
        return 'field_id';
    }

    public function getPrefixMetadataName()
    {
        return 'resprefix';
    }

    public function getFieldDefinitions($onlyInclude = null)
    {
        return \XF::app()->getCustomFields('resources', null, $onlyInclude)->getFieldDefinitions();
    }

    public function getContainerKey()
    {
        return 'customFields.resources';
    }


    public function executeSearch(array $fieldList, array $metadata = [])
    {
        // run a new search and cache the results
        /** @var Search $search */
        $search = \XF::app()->search();
        return $search->searchResourceIdsByCustomFields($fieldList, $metadata);
    }

    public function setupElasticDsl(array &$dslCondition, array $metadata)
    {
        if (!empty($metadata['__metadata_primary_key_ids']))
        {
            $termKey = is_array($metadata['__metadata_primary_key_ids']) ? 'terms' : 'term';
            $dslCondition[] = [
                $termKey => ['discussion_id' => array_values($metadata['__metadata_primary_key_ids'])]
            ];
        }

        // if we have category IDs specified, get from specific nodes only
        if (!empty($metadata['category_ids']))
        {
            $termKey = is_array($metadata['category_ids']) ? 'terms' : 'term';
            $dslCondition[] = [
                $termKey => ['resource_category' => array_values($metadata['category_ids'])]
            ];
        }
    }

    public function setupParentFinder(Finder $finder, array $metadata)
    {
        if (!empty($metadata['category_ids']))
        {
            $finder->where('resource_category_id', $metadata['category_ids']);
        }

        if (!empty($metadata['__metadata_primary_key_ids']))
        {
            if (is_array($metadata['__metadata_primary_key_ids']))
            {
                $finder->whereIds($metadata['__metadata_primary_key_ids']);
            }
            else
            {
                $finder->whereId($metadata['__metadata_primary_key_ids']);
            }

        }
    }

    public function getItemBatch($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn($db->limit(
            '
				SELECT resource.resource_id
				FROM xf_rm_resource AS resource
				INNER JOIN xf_rm_resource_field_value AS field_value ON field_value.resource_id=resource.resource_id
				WHERE resource.resource_id > ?
				GROUP BY resource.resource_id
				ORDER BY resource.resource_id
			', $batch
        ), $start);
    }

    public function getRebuildStatusMessage()
    {
        return \XF::phrase('alrf_resource_field_cache');
    }

    public function getEntityWith($forView = false)
    {
        return ['ContentEntity', 'FieldEntity'];
    }

    /**
     * @param Entity|ResourceFieldIndex $entity
     * @return int
     */
    public function getContentUserId(Entity $entity)
    {
        return $entity->ContentEntity->user_id;
    }

    /**
     * @param Entity|ResourceFieldIndex $entity
     * @return int
     */
    public function getContentDiscussionId(Entity $entity)
    {
        return $entity->ContentEntity->getEntityId();
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('resource_category', MetadataStructure::INT);
    }

    /**
     * @param BaseFieldIndexEntity $entity
     * @param array $metadata
     * @return array
     */
    public function getMetaData(BaseFieldIndexEntity $entity, array $metadata)
    {
        /** @var ResourceItem $contentEntity */
        $contentEntity = $entity->ContentEntity;
        $metadata['resource_category'] = $contentEntity->resource_category_id;

        return $metadata;
    }

    public function getFilterName()
    {
        return 'rf';
    }

    public function isSupportedCustomFieldHolder(Entity $entity)
    {
        return ($entity instanceof ResourceItem);
    }

    public function getOptionPrefix()
    {
        return 'alrf';
    }

    public function getAddonId()
    {
        return 'AL/ResourceFilter';
    }

    public function getFilterLocationFromViewParams(array $params)
    {
        $defaultLocation = FilterApp::getOptionProvider()->getOption($this->getOptionPrefix() . '_filter_location');

        if (empty($params['category']))
        {
            return $defaultLocation;
        }

        if (!$params['category']->effective_filter_location)
        {
            return $defaultLocation;
        }

        return $params['category']->effective_filter_location;
    }

    public function getFieldCacheFromViewParams(array $params)
    {
        if (!empty($params['category']))
        {
            return (array) $params['category']->field_cache;
        }

        if (empty($params['categories']))
        {
            return [];
        }

        return App::getContextProvider()->getCommonFieldsInCategories($params['categories']);
    }

    /**
     * @param Entity|\XFRM\Entity\Category $category
     * @return mixed|null
     */
    public function getFieldCacheForCategory(Entity $category)
    {
        return $category->field_cache;
    }


    public function getIncludeSubCategoryKeyName()
    {
        return 'child_categories';
    }

    public function getCategoriesKeyName()
    {
        return 'categories';
    }

    public function getSearchCategories(array $categoryIds, $includeChildren)
    {
        $allCategories = \XF::repository('XFRM:Category')->getViewableCategories()->toArray();

        if (empty($categoryIds))
        {
            $categoryIds = array_keys($allCategories);
        }
        else if ($includeChildren)
        {
            $nodeTree = \XF::repository('XFRM:Category')->createCategoryTree($allCategories);

            foreach ($categoryIds AS $categoryId)
            {
                $categoryIds = array_merge($categoryIds, $nodeTree->childIds($categoryId));
            }

            $categoryIds = array_unique($categoryIds);
        }

        $allCategories = array_filter($allCategories, function (Category $category) use ($categoryIds)
        {
            return in_array($category->resource_category_id, $categoryIds);
        });

        return $allCategories;
    }

    public function getParamsForLocation($filterLocation, array $viewParams, Controller $controller, array &$filters)
    {
        return [];
    }

    public function getCategoryMetadataKey()
    {
        return 'category';
    }

    public function preSearch(Query $query, array &$metadata, array &$constraints)
    {
        // no further tweaks are needed, as both keywords and tags are handled independent of content types
    }

    public function getMaxResultCount()
    {
        return \XF::options()->alrf_max_limit ? \XF::options()->alrf_max_limit : null;
    }

    public function getKeywordSearchSetting()
    {
        return \XF::options()->alrf_keyword_search;
    }

    public function getTagSearchSetting()
    {
        return \XF::options()->alrf_tag_search;
    }

    public function getMultiPrefixEnabledSetting()
    {
        return \XF::options()->alrf_multi_prefix_search;
    }

    public function getMultiCategorySearchModeSetting()
    {
        return \XF::options()->alrf_multi_category_field_mode;
    }

    public function getPrefixEntityName()
    {
        return 'XFRM:ResourcePrefix';
    }

    public function getGoogleApiKeySetting()
    {
        // Use location field option
        return \XF::options()->allf_google_api_key;
    }

    public function countFacetsByDiscussionIds(array $discussionIds, array $fieldIds)
    {
        // run a new search and cache the results
        /** @var Search $search */
        $search = \XF::app()->search();
        return $search->countFacetsByResourceIds($discussionIds, $fieldIds);
    }

    public function getFacetedSearchSetting()
    {
        return \XF::options()->alrf_faceted_search;
    }

    public function getAutoHideIfEmptySetting()
    {
        return \XF::options()->alrf_auto_hide_if_empty;
    }

    public function getTotalCountIndicatorSetting()
    {
        return \XF::options()->alrf_total_count_indicator;
    }

    public function getNumberFormattingExclusionListSetting()
    {
        return \XF::options()->alrf_number_formatting_exclusion_list;
    }
}

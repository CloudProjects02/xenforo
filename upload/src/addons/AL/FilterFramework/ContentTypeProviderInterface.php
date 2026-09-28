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


namespace AL\FilterFramework;

use AL\FilterFramework\Entity\BaseFieldIndexEntity;
use AL\FilterFramework\Field\AbstractField;
use AL\FilterFramework\Service\ExtendedAttributeSearcher;
use XF\CustomField\Definition;
use XF\Mvc\Controller;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Search\MetadataStructure;
use XF\Search\Query\Query;

interface ContentTypeProviderInterface
{
    public function getContentEntityName();

    public function getCategoryEntityName();

    public function getContentType();

    public function getContentPrimaryKeyName();

    public function getCategoryContentType();

    public function getIndexContentType();

    public function getFieldEntityName();

    public function getIndexEntityName();

    public function getColorSimilarityIndex();

    public function getPhraseForOption($field, $option);

    public function getFieldEntityPrimaryKeyName();

    /**
     * If multiple prefixes are supported, this should return the metadata key that stores the prefix in the index
     * Check for the name at \*\Search\Data\*::setupMetadataStructure.
     *
     * @return string
     */
    public function getPrefixMetadataName();

    /**
     * @param mixed|null $onlyInclude
     *
     * @return Definition[]
     *                      Should return array of field definitions for this content type
     */
    public function getFieldDefinitions($onlyInclude = null);

    /**
     * The key by which the container holds the list of field definition.
     * Used to invalidate the cache after cache rebuild.
     *
     * @return string
     */
    public function getContainerKey();

    /**
     * @param AbstractField[] $fieldList
     * @param array           $metadata  Any additional data passed to content type handler
     *
     * @return array
     *               Should return an array with content IDs matching the search of fieldList
     */
    public function executeSearch(array $fieldList, array $metadata = []);

    public function countFacetsByDiscussionIds(array $discussionIds, array $fieldIds);

    public function setupElasticDsl(array &$dslCondition, array $metadata);

    public function setupParentFinder(Finder $finder, array $metadata);

    public function getItemBatch($start, $batch);

    public function getRebuildStatusMessage();

    public function getEntityWith($forView = false);

    public function getContentUserId(Entity $entity);

    public function getContentDiscussionId(Entity $entity);

    public function setupMetadataStructure(MetadataStructure $structure);

    public function getMetaData(BaseFieldIndexEntity $entity, array $metadata);

    public function getFilterName();

    public function isSupportedCustomFieldHolder(Entity $entity);

    public function getOptionPrefix();

    public function getAddonId();

    /**
     * Should return filter location settings based on context params
     * Return the default if no category/special location information is found in the params
     * Called from controllers responsible for rendering filter forms.
     *
     * @return string
     */
    public function getFilterLocationFromViewParams(array $params);

    public function getFieldCacheFromViewParams(array $params);

    public function getFieldCacheForCategory(Entity $category);

    public function getIncludeSubCategoryKeyName();

    public function getCategoriesKeyName();

    public function getSearchCategories(array $categoryIds, $includeChildren);

    public function getParamsForLocation($filterLocation, array $viewParams, Controller $controller, array &$filters);

    public function getCategoryMetadataKey();

    /**
     * Setup pre-search query object if required.
     *
     * @param array $metadata    The metadata provided by user to tweak the search
     * @param array $constraints Array of constrains found. Each new condition should add a new constrain
     *
     * @see ExtendedAttributeSearcher::preSearch
     */
    public function preSearch(Query $query, array &$metadata, array &$constraints);

    /**
     * @return int|null The maximum number of items to show on filtered views. Null for unlimited.
     */
    public function getMaxResultCount();

    public function getKeywordSearchSetting();

    public function getMultipleKeywordSupport(): bool;

    public function getTagSearchSetting();

    public function getMultiPrefixEnabledSetting();

    /**
     * The setting defines, if the forms on index and search pages should show the custom fields
     * which are common by all categories, or all custom fields that are available in all categories being searched
     * If "shared" only the fields available in all categories will be shown, if "all" then all fields will be available.
     *
     * @return string shared|all
     */
    public function getMultiCategorySearchModeSetting();

    /**
     * @return string
     */
    public function getPrefixEntityName();

    /**
     * The API key for Geolocation requests.
     *
     * @return string
     */
    public function getGoogleApiKeySetting();

    public function getFacetedSearchSetting();

    public function getAutoHideIfEmptySetting();

    public function getTotalCountIndicatorSetting();

    public function getNumberFormattingExclusionListSetting();
}

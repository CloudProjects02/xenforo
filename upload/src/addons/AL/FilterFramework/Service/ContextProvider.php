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
use AL\FilterFramework\Data\FacetedSearchData;
use AL\FilterFramework\Entity\BaseFieldIndexEntity;
use AL\FilterFramework\FilterApp;
use AL\FilterFramework\FilterSetupInterface;
use AL\FilterFramework\Flags;
use AL\LocationField\Constants;
use AL\LocationField\Service\Configuration;
use XF\App;
use XF\CustomField\Definition;
use XF\Entity\Option;
use XF\Entity\WidgetDefinition;
use XF\Mvc\Controller;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Structure;
use XF\Repository\AbstractField;
use XF\Search\Query\MetadataConstraint;
use XF\Service\AbstractService;

class ContextProvider extends AbstractService
{
    protected $contentTypeProvider;

    protected $fieldIndexer;

    /**
     * @var FieldIndexer
     */
    protected $indexer;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function skipCustomFieldPermissionCheck()
    {
        // skip the check if we are running in the CLI
        if (PHP_SAPI === 'cli')
        {
            return true;
        }

        // skip the check if we are running in the admin area
        if (!$this->app instanceof \XF\Pub\App)
        {
            return true;
        }

        return false;
    }

    public function getFieldIdFromInputName($input_name)
    {
        $filterName = $this->contentTypeProvider->getFilterName();
        // Remove [] from the end of $input_name if it exists
        $input_name = preg_replace('/\[]$/', '', $input_name);
        if (preg_match('#' . preg_quote($filterName, '#') . '.*?\[(.*?)]$#', $input_name, $match))
        {
            return $match[1];
        }

        return null;
    }

    public function getFieldOptionSuggestions($page, $fieldId, $search)
    {
        $accented = ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή'];
        $accentsRemoved = ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η'];

        // Remove accents from search
        $search = str_replace($accented, $accentsRemoved, $search);

        $field = $this->getCachedFieldDefinitions([$fieldId]);

        if (!isset($field[$fieldId]))
        {
            return [
                'results' => [],
                'pagination' => [
                    'more' => false,
                ],
            ];
        }

        $choices = $field[$fieldId]->field_choices ?? [];

        $finalChoices = [];

        array_walk($choices, function ($choice, $key) use (&$finalChoices, $search, $accented, $accentsRemoved) {
            $choiceCleared = str_replace($accented, $accentsRemoved, $choice);
            if ($search)
            {
                if (function_exists('mb_stripos'))
                {
                    $match = mb_stripos($choiceCleared, $search);
                }
                else
                {
                    $match = stripos($choiceCleared, $search);
                }

                if ($match === false)
                {
                    return;
                }
            }

            $finalChoices[] = [
                'id' => $key,
                'text' => (string) $choice,
            ];
        });

        // split into pages
        $pages = array_chunk($finalChoices, 50, true);

        $pageIndex = $page - 1;

        if (!isset($pages[$pageIndex]))
        {
            $more = false;
            $results = [];
        }
        else
        {
            $results = array_values($pages[$pageIndex]);
            $more = true;
            if ($pageIndex === count($pages) - 1)
            {
                $more = false;
            }
        }

        return [
            'results' => $results,
            'pagination' => [
                'more' => $more,
            ],
        ];
    }

    public function getMetaData(BaseFieldIndexEntity $entity)
    {
        $field = $entity->FieldEntity;
        if (!$field)
        {
            return [];
        }

        $typeProvider = FilterApp::getTypeProvider();
        $fieldValue = $entity->field_value;

        $metaData = [
            'field_id' => $entity->FieldEntity->field_id,
            'field_choice_single' => '',
            'field_choice_multiple' => '',
        ]
            + FilterApp::getFieldIndexer($this->contentTypeProvider)->getSqlIndexDefaultMetaData();

        if ($typeProvider->isColor($field) && $colorData = FilterApp::getColorConverter()->rgbToLabCie($fieldValue))
        {
            array_walk($colorData, function ($color, $index) use (&$metaData) {
                $metaData['field_color_' . $index] = $color;
            });
        }
        elseif ($typeProvider->isLocationField($field))
        {
            /** @var Definition|\AL\LocationField\XF\CustomField\Definition $fieldDefinition */
            $fieldDefinition = FilterApp::getContextProvider($this->contentTypeProvider)->getFieldDefinitionById($field->field_id);
            if ($fieldDefinition)
            {
                $data = $fieldDefinition->getLocationData($fieldValue);
                $metaData['field_location_country_code'] = $data->getCountryCode();
                $metaData['field_location_state_id'] = $data->getStateId();
                $metaData['field_location_city_id'] = $data->getCityId();
                $metaData['field_location_zip_code'] = $data->getZipCode();
                $metaData['field_location_street_address'] = $data->getStreetAddress();
                $metaData['field_location_full_address'] = $fieldDefinition->getFormattedValue($fieldValue);

                /** @var GeoLocator $locator */
                $locator = FilterApp::getGeoLocator();
                $coordinates = $locator->getAddressCoordinates(
                    $metaData['field_location_full_address'],
                    $metaData['field_location_country_code'],
                    $fieldValue['place_id'] ?? ''
                );

                if ($coordinates !== null)
                {
                    $metaData['field_location_lat'] = $coordinates['lat'];
                    $metaData['field_location_lng'] = $coordinates['lng'];
                }
            }
        }
        elseif ($typeProvider->isInteger($field))
        {
            $metaData['field_int'] = $typeProvider->getInt($field, $fieldValue);
        }
        elseif ($typeProvider->isFloat($field))
        {
            $metaData['field_float'] = $typeProvider->getFloat($field, $fieldValue);
        }
        elseif ($typeProvider->isMultipleOption($field))
        {
            $metaData['field_choice_multiple'] = TypeProvider::$choiceSeparator . implode(TypeProvider::$choiceSeparator, (array) $fieldValue) . TypeProvider::$choiceSeparator;
        }
        elseif ($typeProvider->isSingleOption($field))
        {
            $metaData['field_choice_single'] = $fieldValue;
        }

        return $this->contentTypeProvider->getMetaData($entity, $metaData);
    }

    /**
     * @param mixed $fieldId
     *
     * @return Definition|null
     */
    public function getFieldDefinitionById($fieldId)
    {
        $fields = $this->getCachedFieldDefinitions([$fieldId]);
        if (isset($fields[$fieldId]))
        {
            return $fields[$fieldId];
        }

        return null;
    }

    /**
     * Returns cached field data. Rebuilds the cache if it is missing.
     *
     * @param null $onlyInclude
     */
    public function getCachedFieldDefinitions($onlyInclude = null)
    {
        $definitions = $this->contentTypeProvider->getFieldDefinitions($onlyInclude);

        if (empty($definitions))
        {
            // no fields anyway, no need to check for cache validity
            return $definitions;
        }

        // check for cache validity only once
        static $cacheChecked = false;
        if ($cacheChecked)
        {
            return $definitions;
        }
        $cacheChecked = true;

        // take the first field
        /** @var Definition $first */
        $first = reset($definitions);

        // see if FieldData key we add for each custom field exists in this one
        if ($first->offsetExists('FieldData') === false)
        {
            /** @var AbstractField $repo */
            $repo = \XF::repository($this->contentTypeProvider->getFieldEntityName());
            $repo->rebuildFieldCache();

            // invalidating the container cache will trigger a rebuild
            \XF::app()->container()->decache($this->contentTypeProvider->getContainerKey());
            $definitions = $this->getCachedFieldDefinitions($onlyInclude);
        }

        return $definitions;
    }

    /**
     * Fix single/multiple selection type issues.
     *
     * @param mixed $field_id
     * @param mixed $value
     */
    public function fixFieldValueType($field_id, $value)
    {
        $value = FilterApp::getInputTransformer($this->contentTypeProvider)->getNormalizedFieldValue($value);

        $fields = $this->getCachedFieldDefinitions();

        if (!isset($fields[$field_id]))
        {
            // field not found, nothing we can do about fixing the value
            return $value;
        }

        /** @var Definition $field */
        $definition = $fields[$field_id];

        $fieldData = $definition->offsetGet('FieldData');

        if (!$fieldData || $fieldData['content_type'] !== $this->contentTypeProvider->getContentType())
        {
            // Another type of field
            return $value;
        }

        $is_array = is_array($value);

        if ($definition->type_group === 'single' && $is_array)
        {
            // array is saved for a field which was originally saved as single-type
            // take the first item from the array
            $keys = array_keys($value);
            $value = $keys ? $keys[0] : '';
        }
        elseif ($definition->type_group === 'multiple')
        {
            $field_choices = $definition->field_choices;

            if (!$is_array)
            {
                // the field was single-selection value, but later is converted to multiple-selection
                // create an array from the value
                if (isset($field_choices[$value]))
                {
                    $value = [
                        $value => $value,
                    ];
                }
                else
                {
                    // the options don't match, just an empty array to prevent errors
                    $value = [];
                }
            }

            // sanitize the values that don't exist in the choices
            foreach ($value as $key => $v)
            {
                if (!isset($field_choices[$key]))
                {
                    unset($value[$key]);
                }
            }
        }

        return $value;
    }

    public function runRebuildById($id)
    {
        $item = \XF::app()->em()->find($this->contentTypeProvider->getContentEntityName(), $id);

        if (!$item)
        {
            return false;
        }

        FilterApp::getFieldIndexer($this->contentTypeProvider)->reIndexEntityFields($item);

        return true;
    }

    public function getCommonFieldsInCategories($categories)
    {
        $fields = null;

        foreach ($categories as $category)
        {
            if ($fields === null)
            {
                $fields = $this->contentTypeProvider->getFieldCacheForCategory($category);
            }
            else
            {
                if ($this->contentTypeProvider->getMultiCategorySearchModeSetting() === 'all')
                {
                    $fields += $this->contentTypeProvider->getFieldCacheForCategory($category);
                }
                else
                {
                    $fieldCache = $this->contentTypeProvider->getFieldCacheForCategory($category);

                    $fields = array_intersect((array) $fields, $fieldCache);
                }
            }
            // no fields detected so far
            if (empty($fields) && $this->contentTypeProvider->getMultiCategorySearchModeSetting() !== 'all')
            {
                break;
            }
        }

        if ($fields === null)
        {
            return [];
        }

        return $fields;
    }

    public function filterOutUnknownCustomFields(array $filters, array $knownCustomFields)
    {
        foreach ($filters as $key => $field)
        {
            if (in_array($key, ['__keywords', '__tags', '__metadata_primary_key_ids', Flags::MULTI_PREFIX_KEY]))
            {
                continue;
            }

            if (!array_key_exists($key, $knownCustomFields))
            {
                unset($filters[$key]);
            }
        }

        return $filters;
    }

    public function saveFilterOption(Option $option)
    {
        if ($option->option_id === $this->contentTypeProvider->getOptionPrefix() . '_filter_location' && $option->option_value === 'sidebar')
        {
            $this->assertFilterWidget();
        }
    }

    public function postSaveNode(Entity $node)
    {
        if ($node->filter_location === 'sidebar')
        {
            // ensure we have the default widget when sidebar is activated
            \XF::runOnce($this->contentTypeProvider->getOptionPrefix() . '-default-widget', function () {
                $this->assertFilterWidget();
            });
        }
    }

    public function saveWidgetDefinition(WidgetDefinition $widgetDefinition, $instanceName)
    {
        // make sure the default widget exists when we create the definition class
        if ($widgetDefinition->isInsert() && $widgetDefinition->definition_class === $instanceName)
        {
            $this->assertFilterWidget();
        }
    }

    public function assertFilterWidget()
    {
        $xfAddOn = \XF::app()->addOnManager()->getById($this->contentTypeProvider->getAddonId());
        if (!$xfAddOn)
        {
            return;
        }

        /** @var FilterSetupInterface $setup */
        $setup = $xfAddOn->getSetup();
        $setup->assertFilterWidget();
    }

    public function getWidgetViewParams(array $widgetParams)
    {
        $filterLocation = $this->contentTypeProvider->getFilterLocationFromViewParams($widgetParams);

        if ($filterLocation !== 'sidebar')
        {
            return [];
        }

        return FilterApp::getRequestHelper($this->contentTypeProvider)->getFieldParams(
            $this->contentTypeProvider->getFieldCacheFromViewParams($widgetParams)
        );
    }

    /**
     * @return array
     *               Get from memory the prepared params passed to the content template
     */
    public function getPageContainerViewParams()
    {
        $viewParamsCache = $this->getContextParams();
        if ($viewParamsCache === null)
        {
            return [];
        }

        $viewParamsCache[$this->contentTypeProvider->getFilterName() . '_active'] = true;

        return (array) $viewParamsCache;
    }

    /**
     * @param array  $input       The array containing search criteria
     * @param string $criteriaKey The key under which the criteria are stored
     *
     * @return array Should contain key 'fields' with the custom fields to show on search page
     */
    public function getFilterParamsForSearch($input, $criteriaKey = 'c')
    {
        // invalid input array is provided
        if (!isset($input[$criteriaKey]))
        {
            return [];
        }

        $criteriaArray = $input[$criteriaKey];

        $includeSubCategoryKeyName = $this->contentTypeProvider->getIncludeSubCategoryKeyName();
        $categoriesKeyName = $this->contentTypeProvider->getCategoriesKeyName();

        $includeChildren = isset($criteriaArray[$includeSubCategoryKeyName]) ? (bool) $criteriaArray[$includeSubCategoryKeyName] : false;
        $categoryIds = isset($criteriaArray[$categoriesKeyName]) ? (array) $criteriaArray[$categoriesKeyName] : [];
        $categoryIds = array_filter($categoryIds); // remove empty entries

        $allCategories = $this->contentTypeProvider->getSearchCategories($categoryIds, $includeChildren);

        // only the fields available in ALL forums to be searched can be shown in the form
        $availableFieldIds = FilterApp::getContextProvider($this->contentTypeProvider)->getCommonFieldsInCategories($allCategories);

        $fieldDefinitions = $this->getCachedFieldDefinitions($availableFieldIds);

        $fieldDefinitions = array_filter($fieldDefinitions, function (Definition $field) {
            return !empty($field['FieldData']['allow_search']);
        });

        $filterName = $this->contentTypeProvider->getFilterName();

        return [
            'fields' => $fieldDefinitions,
            'filterSet' => $criteriaArray[$filterName] ?? [],
        ];
    }

    public function getQueryConstrains()
    {
        try
        {
            return FilterApp::getRegistryProvider()->get('queryConstrains' . $this->contentTypeProvider->getFilterName());
        }
        catch (\Exception $exception)
        {
            return null;
        }
    }

    public function setQueryConstrains(array $constrains)
    {
        FilterApp::getRegistryProvider()->set('queryConstrains' . $this->contentTypeProvider->getFilterName(), $constrains);
    }

    public function filterSearchResults(array $result, array $constrains)
    {
        $itemIds = [];
        array_walk($result, function ($resultItem) use (&$itemIds) {
            if ($resultItem[0] === $this->contentTypeProvider->getContentType())
            {
                $itemIds[] = $resultItem[1];
            }
        });

        $service = FilterApp::getCachedSearchProvider($this->contentTypeProvider);
        $filteredItemIds = $service->executeSearch(
            $constrains,
            ['__metadata_primary_key_ids' => $itemIds]
        );

        if ($filteredItemIds === null)
        {
            // no filters present in the search
            return $result;
        }

        if (count($filteredItemIds) !== count($itemIds))
        {
            $result = array_filter($result, function ($resultItem) use ($filteredItemIds) {
                if ($resultItem[0] !== $this->contentTypeProvider->getContentType())
                {
                    return true;
                }

                return in_array($resultItem[1], $filteredItemIds);
            });
        }

        return $result;
    }

    /**
     * @param MetadataConstraint[] $metadataConstraint
     *
     * @return array|bool
     */
    public function getDiscussionIdsByCategoryMetadata(array $filterData, array $metadataConstraint)
    {
        $categoryIds = [];
        array_map(function (MetadataConstraint $constraint) use (&$categoryIds) {
            if ($constraint->getKey() === $this->contentTypeProvider->getCategoryMetadataKey())
            {
                $categoryIds = array_merge($categoryIds, $constraint->getValues());
            }
        }, $metadataConstraint);

        $searchMeta = [];

        if (!empty($categoryIds))
        {
            $searchMeta['category_ids'] = $categoryIds;
        }

        // get all discussions matching the criteria
        $discussionIds = FilterApp::getCachedSearchProvider($this->contentTypeProvider)->executeSearch(
            $filterData,
            $searchMeta
        );

        if ($discussionIds === null)
        {
            return false;
        }

        if (empty($discussionIds))
        {
            // no items found, add an unreal condition to cause no results returned
            $discussionIds = [0];
        }

        return $discussionIds;
    }

    public function setContextParams($params = [], $override = false)
    {
        $registerProvider = FilterApp::getRegistryProvider();
        $viewParamCacheName = $this->contentTypeProvider->getFilterName() . '_request';
        $existingParams = $registerProvider->isRegistered($viewParamCacheName) ? $registerProvider->get($viewParamCacheName) : [];
        // add into existing params if any in the cache
        if ($override)
        {
            $viewParams = $params + $existingParams;
        }
        else
        {
            $viewParams = $existingParams + $params;
        }

        $registerProvider->set($viewParamCacheName, $viewParams);

        return $viewParams;
    }

    public function getContextParams()
    {
        $registerProvider = FilterApp::getRegistryProvider();
        $viewParamCacheName = $this->contentTypeProvider->getFilterName() . '_request';
        if (!$registerProvider->isRegistered($viewParamCacheName))
        {
            return null;
        }

        return $registerProvider->get($viewParamCacheName);
    }

    /**
     * @param null $facetedCounts
     *
     * @return array
     *               Populate view params with info needed to render a filter form
     */
    public function setupViewParams(array $viewParams, Controller $controller, $facetedCounts = null)
    {
        $filterLocation = $this->contentTypeProvider->getFilterLocationFromViewParams($viewParams);

        $filters = $viewParams['filters'] ?? [];

        // we expect the filter name in the response, prevent RSS error otherwise
        $filterName = $this->contentTypeProvider->getFilterName();
        if (empty($filters[$filterName]))
        {
            $filters[$filterName] = [];
        }

        $viewParams['filter_location'] = $filterLocation;

        // set the info in a global service to access in different places to build the filter

        $viewParams = $this->setContextParams($viewParams);

        $fieldCache = $this->contentTypeProvider->getFieldCacheFromViewParams($viewParams) ?: [];

        // add params needed to render the filter
        $viewParams = array_merge(
            $viewParams,
            FilterApp::getRequestHelper($this->contentTypeProvider)->getFieldParams($fieldCache, $filters)
        );

        // params can be set based on location for the main template
        $viewParams += $this->contentTypeProvider->getParamsForLocation(
            $filterLocation,
            $viewParams,
            $controller,
            $filters
        );

        $this->setContextParams($viewParams);

        return $viewParams;
    }

    public function executeFacetedSearch(Finder $finder, $fieldCache = [], $includeLocation = false)
    {
        $contentTypeProvider = $this->contentTypeProvider;

        // Store the information in a data class to access later
        /** @var FacetedSearchData $data */
        $data = \XF::app()->data('AL\FilterFramework:FacetedSearchData');
        $data->setContentTypeProvider($contentTypeProvider);

        if ($contentTypeProvider->getFacetedSearchSetting() && $fieldCache)
        {
            $primaryKeyName = $contentTypeProvider->getContentPrimaryKeyName();
            // Execute the search before applying the custom fields
            $clonedFinder = clone $finder;
            $discussionIds = $clonedFinder->fetchColumns($primaryKeyName);
            $discussionIds = array_map(static function (array $item) use ($primaryKeyName) {
                return $item[$primaryKeyName];
            }, $discussionIds);

            $facetedCounts = FilterApp::getFacetedSearchProvider(
                $contentTypeProvider
            )->getFacetCounts(
                $discussionIds,
                $fieldCache,
                $includeLocation
            );

            $data->setFacetedSearchData($facetedCounts);
        }
    }

    public function applyCategoryFilters(
        Finder $finder,
        array $filters,
        $categoryIds = [],
        $fieldCache = []
    ) {
        $filerName = $this->contentTypeProvider->getFilterName();

        $filterData = $filters[$filerName] ?? [];

        if (!empty($filterData))
        {
            // store the array in a separate variable for quick access
            $discussionIds = FilterApp::getCachedSearchProvider($this->contentTypeProvider)->executeSearch(
                $filterData,
                ['category_ids' => $categoryIds]
            );

            if ($discussionIds === null)
            {
                // there was no filter
                return;
            }

            if (empty($discussionIds))
            {
                // no items have been found, add an unreal condition to filter
                $discussionIds = [0];
            }

            $finder->whereIds($discussionIds);
        }
    }

    public function setupUrlConstraints(array &$urlConstraints)
    {
        $filterName = $this->contentTypeProvider->getFilterName();

        if (empty($urlConstraints[$filterName]))
        {
            unset($urlConstraints[$filterName]);

            return;
        }

        // store the original version in a variable to prevent possible issues with normalization being called two times on the same values
        $fields = $urlConstraints[$filterName];

        // normalization will remove all empty/invalid values
        FilterApp::getInputTransformer($this->contentTypeProvider)->normalizeInput($urlConstraints[$filterName]);

        if (empty($urlConstraints[$filterName]))
        {
            unset($urlConstraints[$filterName]);

            return;
        }

        // set the input data into the query object to access it later
        $this->setQueryConstrains($fields);
    }

    public function setupFieldStructure(Structure $structure)
    {
        $structure->relations['FieldData'] = [
            'entity' => 'AL\FilterFramework:FieldData',
            'type' => Entity::TO_ONE,
            'conditions' => [
                ['field_id', '=', '$' . $this->contentTypeProvider->getFieldEntityPrimaryKeyName()],
                ['content_type', '=', $this->contentTypeProvider->getContentType()],
            ],
            'primary' => true,
        ];
        $structure->getters += [
            'is_sortable' => true,
        ];
    }

    public function setupFieldHolderStructure(Structure $structure)
    {
        $structure->relations['CustomFieldIndex'] = [
            'entity' => $this->contentTypeProvider->getIndexEntityName(),
            'type' => Entity::TO_MANY,
            'conditions' => [
                ['content_id', '=', '$' . $this->contentTypeProvider->getContentPrimaryKeyName()],
                ['content_type', '=', $this->contentTypeProvider->getContentType()],
            ],
            'key' => 'field_id', // represents field_id in CustomFieldIndex, used to resolve the column in case this is joined using CustomFieldIndex|field_id syntax
            'primary' => true,
        ];
    }

    public function getFilterableFieldFromCache(array $fieldCache)
    {
        $fields = $this->getCachedFieldDefinitions($fieldCache);

        return array_filter((array) $fields, function (Definition $field) {
            if (!empty($field['FieldData']['allow_filter']))
            {
                // Location fields should not be shown unless Google API key is provided
                /** @noinspection IfReturnReturnSimplificationInspection */
                if (
                    $field->field_type === 'location' && !$this->contentTypeProvider->getGoogleApiKeySetting()
                    && method_exists($field, 'getLocationConfiguration')
                ) {
                    /** @var Configuration $configuration */
                    $configuration = $field->getLocationConfiguration();
                    // Location fields are filterable if we have Google API Key or search format selected Country/State/City selection
                    if ($configuration->getSearchFormatOption() === Constants::SEARCH_FORMAT_RANGE)
                    {
                        return false;
                    }
                }

                return true;
            }

            return false;
        });
    }

    public function getPreparedFieldCache(array $field_cache)
    {
        $groups = [];

        $fields = $this->getCachedFieldDefinitions();

        foreach ($field_cache as $fieldId => $displayOptions)
        {
            if (!isset($fields[$fieldId]))
            {
                continue;
            }

            if (empty($displayOptions['enabled']))
            {
                continue;
            }
            unset($displayOptions['enabled']);

            foreach ($displayOptions as $locationId => $locationOptions)
            {
                if (empty($locationOptions['enabled']))
                {
                    continue;
                }
                unset($locationOptions['enabled']);
                foreach ($locationOptions as $groupId => $groupEnabled)
                {
                    if (empty($groupEnabled))
                    {
                        continue;
                    }
                    $groups[$locationId][$groupId][$fieldId] = $fields[$fieldId];
                }
            }
        }

        return $groups;
    }

    public function getSortOptions(array $fieldCache)
    {
        static $sortOptions;

        if ($sortOptions === null)
        {
            $fields = $this->getCachedFieldDefinitions($fieldCache);

            $sortableFields = array_filter((array) $fields, function (Definition $field) {
                return !empty($field['FieldData']['allow_sorting']) && $field['is_sortable'];
            });

            $sortOptions = [];

            foreach ($sortableFields as $fieldId => $fieldInfo)
            {
                $column = FilterApp::getTypeProvider()->getIndexSortingColumnName($fieldInfo);
                if ($column !== false)
                {
                    $sortOptions[$this->contentTypeProvider->getFilterName() . '_' . $fieldId] = "CustomFieldIndex|{$fieldId}.{$column}";
                }
            }
        }

        return $sortOptions;
    }

    public function applySort($sort) {}
}

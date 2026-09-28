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
use AL\FilterFramework\FilterApp;
use XF\App;
use XF\CustomField\Definition;
use XF\Service\AbstractService;

class FacetedSearchProvider extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function filterChoices(array $choices, $facetedData = null)
    {
        if ($facetedData === null)
        {
            return [];
        }

        return $choices;
    }

    /**
     * @return array
     *               Filter out the custom fields which should don't have any faceted data available
     */
    public function filterAvailableFields(array $fields, array $filterSet)
    {
        if (
            !$this->contentTypeProvider->getFacetedSearchSetting()
            || $this->contentTypeProvider->getAutoHideIfEmptySetting() !== 'hide'
        ) {
            // Automatic hiding of fields is disabled, don't filter them
            return $fields;
        }

        /** @var FacetedSearchData $data */
        $data = \XF::app()->data('AL\FilterFramework:FacetedSearchData');

        $searchData = $data->getSearchData();

        if ($searchData === null)
        {
            // means the data was not populated in some case, don't change the filters in any way
            return $fields;
        }

        foreach ($searchData as $fieldId => $fieldData)
        {
            if (!isset($fieldData['count']))
            {
                // the total count was not set, invalid data
                continue;
            }

            if ($fieldData['count'] === 0 && !array_key_exists($fieldId, $filterSet))
            {
                // remove the fields, which are not part of the active filter
                // and there are no items available
                unset($fields[$fieldId]);
            }
        }

        return $fields;
    }

    public function prepareChoices(array $choices, $facetedData = null, $useLabel = true)
    {
        return array_map(static function ($choice) use ($useLabel) {
            return \XF::escapeString($choice) . ($useLabel ? ' <span class="label label--subtle">0</span>' : ' (0)');
        }, $choices);
    }

    public function getFacetFieldIds(array $fieldIds, $includeLocation = false)
    {
        $typeProvider = FilterApp::getTypeProvider();
        $fields = array_filter(
            $this->contentTypeProvider->getFieldDefinitions($fieldIds),
            static function (Definition $field) use ($typeProvider, $includeLocation) {
                return $typeProvider->isMultipleOption($field) || $typeProvider->isSingleOption($field) || ($typeProvider->isLocationField($field) && $includeLocation);
            }
        );

        return array_keys($fields);
    }

    public function getEmptyFacetData(array $fieldIds)
    {
        // No items, no search can provide any result, return a default value
        $defaultData = array_map(function () {
            return [
                'count' => 0,
            ];
        }, $fieldIds);

        return array_combine($fieldIds, $defaultData);
    }

    public function getFacetCounts($itemIds, $fieldIds, $includeLocation = false)
    {
        $fieldIds = $this->getFacetFieldIds($fieldIds, $includeLocation);

        $itemIds = array_map('intval', $itemIds);
        $itemIds = array_filter($itemIds);

        if (empty($itemIds))
        {
            return $this->getEmptyFacetData($fieldIds);
        }

        return $this->contentTypeProvider->countFacetsByDiscussionIds($itemIds, $fieldIds);
    }
}

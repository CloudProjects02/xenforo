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
use AL\FilterFramework\FilterApp;
use XF\App;
use XF\CustomField\Definition;
use XF\Service\AbstractService;

class RequestHelper extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(
        App $app,
        ContentTypeProviderInterface $contentTypeProvider
    ) {
        parent::__construct($app);
        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * @param null $filters
     *
     * @return array
     */
    public function getFieldParams(array $field_cache, &$filters = null)
    {
        $viewParams = [];

        if ($filters === null)
        {
            $contextParams = FilterApp::getContextProvider(
                $this->contentTypeProvider
            )->getContextParams();

            if (empty($contextParams))
            {
                $filters = [];

                return [];
            }

            $viewParams += $contextParams;
            $filters = $viewParams['filters'] ?? [];
        }

        $fields = $this->contentTypeProvider->getFieldDefinitions($field_cache);

        $filterableFields = FilterApp::getContextProvider($this->contentTypeProvider)
            ->getFilterableFieldFromCache(
                $field_cache
            );

        $sortableFields = array_filter((array) $fields, static function (Definition $field) {
            return !empty($field['FieldData']['allow_sorting']) && $field['is_sortable'];
        });
        $additionalSortOptions = array_map(static function (Definition $field) {
            return $field['title'];
        }, $sortableFields);

        $additionalSortOptionKeys = array_map(function ($key) {
            return $this->contentTypeProvider->getFilterName() . '_' . $key;
        }, array_keys($additionalSortOptions));

        $additionalSortOptions = array_combine($additionalSortOptionKeys, $additionalSortOptions);

        $filterName = $this->contentTypeProvider->getFilterName();
        $filterSet = isset($filters[$filterName]) ? (array) $filters[$filterName] : [];

        $filterableFields = FilterApp::getFacetedSearchProvider($this->contentTypeProvider)->filterAvailableFields($filterableFields, $filterSet);

        $additionalFieldInfo = FilterApp::getDisplayFormatter($this->contentTypeProvider)->prepareActiveFilters($filterSet);

        $viewParams = [
            'filter_name' => $this->contentTypeProvider->getFilterName(),
            'fields' => $filterableFields,
            'sortableFields' => $sortableFields,
            'additionalSortOptions' => $additionalSortOptions,
            'filterSet' => $filterSet,
            'additionalFieldInfo' => $additionalFieldInfo,
        ] + $viewParams;

        return $viewParams;
    }
}

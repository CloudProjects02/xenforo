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
use AL\FilterFramework\DataSourceInterface;
use AL\FilterFramework\FilterApp;
use AL\FilterFramework\RootFinder;
use XF\App;
use XF\Service\AbstractService;

/**
 * Class MySqlSource.
 */
class MySqlSource extends AbstractService implements DataSourceInterface
{
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function countFacets(array $discussionIds, array $fieldIds)
    {
        // MySQL implementation is based on field index directly
        // with a direct query and aggregation via PHP
        $discussionIds = array_filter($discussionIds);
        $fieldIds = array_filter($fieldIds);
        if (empty($discussionIds) || empty($fieldIds))
        {
            return [];
        }

        $allRows = $this->db()->query('
            SELECT content_id,
                   field_id,
                   field_value,
                   field_location_country_code,
                   field_location_state_id,
                   field_location_city_id
            FROM xf_alff_field_index
            WHERE content_type=?
            AND content_id IN(' . $this->db()->quote($discussionIds) . ')
            AND field_id IN(' . $this->db()->quote($fieldIds) . ')
            LIMIT 50000
        ', [
            $this->contentTypeProvider->getContentType(),
        ]); // Some limit to prevent memory issue

        $fields = $this->contentTypeProvider->getFieldDefinitions($fieldIds);
        $typeProvider = FilterApp::getTypeProvider();

        $totalCounts = [];
        while ($row = $allRows->fetch())
        {
            $fieldId = $row['field_id'];

            $field = $fields[$fieldId];
            $isSingleOption = $typeProvider->isSingleOption($field);
            $isMultipleOption = $typeProvider->isMultipleOption($field);
            $isLocation = $typeProvider->isLocationField($field);

            if (!$isSingleOption && !$isMultipleOption && !$isLocation)
            {
                continue;
            }

            if (!isset($totalCounts[$fieldId]))
            {
                $totalCounts[$fieldId] = [
                    'count' => 0,
                ];
            }

            $counts = &$totalCounts[$fieldId];

            ++$counts['count'];

            if ($isSingleOption || $isMultipleOption)
            {
                $options = $isSingleOption ? [$row['field_value']] : unserialize($row['field_value']);
                if (is_array($options))
                {
                    foreach ($options as $option)
                    {
                        if (!isset($counts['options'][$option]))
                        {
                            $counts['options'][$option] = 0;
                        }
                        ++$counts['options'][$option];
                    }
                }
            }

            if ($isLocation)
            {
                $countryCode = $row['field_location_country_code'];

                if (!isset($counts['countries'][$countryCode]))
                {
                    $counts['countries'][$countryCode] = [
                        'count' => 0,
                        'states' => [],
                    ];
                }

                ++$counts['countries'][$countryCode]['count'];

                $state_id = $row['field_location_state_id'];
                if (!isset($counts['countries'][$countryCode]['states'][$state_id]))
                {
                    $counts['countries'][$countryCode]['states'][$state_id] = [
                        'count' => 0,
                        'cities' => [],
                    ];
                }
                ++$counts['countries'][$countryCode]['states'][$state_id]['count'];

                $city_id = $row['field_location_city_id'];

                if (!isset($counts['countries'][$countryCode]['states'][$state_id]['cities'][$city_id]))
                {
                    $counts['countries'][$countryCode]['states'][$state_id]['cities'][$city_id] = 0;
                }

                ++$counts['countries'][$countryCode]['states'][$state_id]['cities'][$city_id];
            }

            unset($counts);
        }

        return $totalCounts;
    }

    public function search(array $fieldList, array $metadata = [])
    {
        $logger = FilterApp::getFilterLogger($this->contentTypeProvider);
        $logger->info('Executing MySQL search with fields: ' . json_encode($fieldList, JSON_PARTIAL_OUTPUT_ON_ERROR) . ' and metadata: ' . json_encode($metadata, JSON_PARTIAL_OUTPUT_ON_ERROR));
        $parentFinder = RootFinder::getRootFinderClone() ?? \XF::finder($this->contentTypeProvider->getContentEntityName());
        $this->contentTypeProvider->setupParentFinder($parentFinder, $metadata);
        $this->getFieldIndexAccessor()->setupParentFinder($parentFinder, $fieldList);
        $parentFinder->pluckFrom($this->contentTypeProvider->getContentPrimaryKeyName());

        $logger->debug('Finder query is: ' . $parentFinder->getQuery());

        // limit the number of items to some sensible upper limit
        $itemIds = $parentFinder->fetch($this->contentTypeProvider->getMaxResultCount())->toArray();

        return array_values($itemIds);
    }

    /**
     * @return FieldIndexAccessor|AbstractService
     *
     * @testable
     */
    public function getFieldIndexAccessor()
    {
        return \XF::service('AL\FilterFramework:FieldIndexAccessor', $this->contentTypeProvider);
    }
}

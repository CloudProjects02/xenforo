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
use AL\FilterFramework\Field\AbstractField;
use AL\FilterFramework\Field\ColorField;
use AL\FilterFramework\Field\FloatField;
use AL\FilterFramework\Field\FreeTextField;
use AL\FilterFramework\Field\IntField;
use AL\FilterFramework\Field\LocationField;
use AL\FilterFramework\Field\MultipleChoiceField;
use AL\FilterFramework\Field\SingleChoiceField;
use AL\FilterFramework\FilterApp;
use AL\FilterFramework\Flags;
use XF\App;
use XF\Service\AbstractService;
use XFES\Elasticsearch\Exception;
use XFES\Listener;

/**
 * Class ElasticSource.
 */
class ElasticSource extends AbstractService implements DataSourceInterface
{
    protected $es;

    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->es = Listener::getElasticsearchApi();
        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function countFacets(array $discussionIds, array $fieldIds)
    {
        $fields = $this->contentTypeProvider->getFieldDefinitions($fieldIds);
        $typeProvider = FilterApp::getTypeProvider();

        $aggregations = [];
        $totalCounts = [];

        foreach ($fields as $field)
        {
            $fieldId = $field->offsetGet('field_id');
            // The default data
            $totalCounts[$fieldId] = [
                'count' => 0,
            ];
            $filter = [
                'bool' => [
                    'must' => [
                        0 => [
                            'term' => [
                                'field_id' => $fieldId,
                            ],
                        ],
                    ],
                ],
            ];

            if ($typeProvider->isSingleOption($field))
            {
                $aggregations[$fieldId] = [
                    'filter' => $filter,
                    'aggregations' => [
                        'choices' => [
                            'terms' => [
                                'field' => 'field_choice_single',
                                'size' => 100000,
                            ],
                        ],
                    ],
                ];
            }
            elseif ($typeProvider->isMultipleOption($field))
            {
                $aggregations[$fieldId] = [
                    'filter' => $filter,
                    'aggregations' => [
                        'choices' => [
                            'terms' => [
                                'field' => 'field_choice_multiple',
                                'size' => 100000,
                            ],
                        ],
                    ],
                ];
            }
            elseif ($typeProvider->isLocationField($field))
            {
                $aggregations[$fieldId] = [
                    'filter' => $filter,
                    'aggregations' => [
                        'country' => [
                            'terms' => [
                                'field' => 'field_location_country_code',
                                'size' => 100000,
                            ],
                            'aggregations' => [
                                'state' => [
                                    'terms' => [
                                        'field' => 'field_location_state_id',
                                        'size' => 100000,
                                    ],
                                    'aggregations' => [
                                        'city' => [
                                            'terms' => [
                                                'field' => 'field_location_city_id',
                                                'size' => 100000,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        if (empty($aggregations))
        {
            // no appropriate type of fields are found
            return $totalCounts;
        }

        $dsl = [];

        // no need to fetch the results, we are interested in aggregations only
        $dsl['size'] = 0;

        if ($this->es->isSingleTypeIndex())
        {
            // in single-type index type is stored as a separate keyword
            $dsl['query']['constant_score']['filter']['bool']['must'] = [
                ['term' => ['type' => $this->contentTypeProvider->getIndexContentType()]],
            ];
        }
        else
        {
            // otherwise type is a built-in value in elastic search
            $dsl['query']['constant_score']['filter']['bool']['must'] = [
                ['type' => ['value' => $this->contentTypeProvider->getIndexContentType()]],
            ];
        }

        $dsl['query']['constant_score']['filter']['bool']['must'][] = [
            'terms' => ['discussion_id' => $discussionIds],
        ];

        $dsl['aggregations'] = $aggregations;

        try
        {
            $logger = FilterApp::getFilterLogger($this->contentTypeProvider);
            $logger->setForceLogToFile(true);
            $logger->info('Logging Elastic search dsl: ' . json_encode($dsl, JSON_PARTIAL_OUTPUT_ON_ERROR));
            $response = $this->es->requestFromIndex('GET', '_search', $dsl)->getBody();
        }
        catch (Exception $e)
        {
            \XF::logException($e, false);
            $response = [];
        }

        if (!$response || !isset($response['aggregations']))
        {
            throw \XF::phrasedException('xfes_search_could_not_be_completed_try_again_later');
        }

        foreach ($response['aggregations'] as $fieldId => $fieldData)
        {
            $field = $fields[$fieldId];
            $isMultiple = $typeProvider->isMultipleOption($field);
            $isSingle = $typeProvider->isSingleOption($field);
            $isLocation = $typeProvider->isLocationField($field);
            $counts = [
                'count' => $fieldData['doc_count'],
            ];
            if ($isMultiple || $isSingle)
            {
                $counts['options'] = [];
                foreach ($fieldData['choices']['buckets'] as $bucket)
                {
                    if ($bucket['key'] === '')
                    {
                        // Empty selection, the count should be subtracted from the total
                        $counts['count'] = max($counts['count'] - $bucket['doc_count'], 0);

                        continue;
                    }

                    if ($isMultiple)
                    {
                        $options = explode(TypeProvider::$choiceSeparator, $bucket['key']);
                    }
                    else
                    {
                        $options = [$bucket['key']];
                    }
                    foreach ($options as $option)
                    {
                        if (!$option)
                        {
                            continue;
                        }

                        if (!isset($counts['options'][$option]))
                        {
                            $counts['options'][$option] = 0;
                        }
                        $counts['options'][$option] += $bucket['doc_count'];
                    }
                }
            }
            elseif ($isLocation)
            {
                $counts['countries'] = [];

                foreach ((array) $fieldData['country']['buckets'] as $country)
                {
                    $countryCode = $country['key'];
                    $counts['countries'][$countryCode] = [
                        'count' => $country['doc_count'],
                        'states' => [],
                    ];
                    foreach ((array) $country['state']['buckets'] as $state)
                    {
                        $counts['countries'][$countryCode]['states'][$state['key']] = [
                            'count' => $state['doc_count'],
                            'cities' => [],
                        ];

                        foreach ((array) $state['city']['buckets'] as $city)
                        {
                            $counts['countries'][$countryCode]['states'][$state['key']]['cities'][$city['key']] = $city['doc_count'];
                        }
                    }
                }
            }

            $totalCounts[$fieldId] = $counts;
        }

        return $totalCounts;
    }

    public function search(array $fieldList, array $metadata = [])
    {
        $logger = FilterApp::getFilterLogger($this->contentTypeProvider);

        $logger->info('Executing ES search with fields: ' . json_encode($fieldList, JSON_PARTIAL_OUTPUT_ON_ERROR) . ' and metadata: ' . json_encode($metadata, JSON_PARTIAL_OUTPUT_ON_ERROR));

        $metadataPrimaryKey = '__metadata_primary_key_ids';

        if (!isset($metadata[$metadataPrimaryKey]))
        {
            $metadata[$metadataPrimaryKey] = [];
        }
        // store the reference, as changing metadata will narrow down every subsequent request range
        $item_ids = &$metadata[$metadataPrimaryKey];

        if (empty($fieldList))
        {
            // no fields to search, only item IDs provided should be returned
            return $item_ids;
        }

        // will hold the fields required to run using MySQL table
        $sqlFilter = [];

        $foundFieldIds = [];

        $initialFieldList = $fieldList;

        // indicates that we are in the first round, so any items found will be used as $item_ids filter for further queries
        $isFirstPass = true;
        $hasElasticConstraints = false; // should be set to true if any field was searched via elastic search engine

        global $TEST_ITEM_ID; // for testing only

        while (true)
        {
            // will hold field_id=>[item_id,...] mapping
            $aggregated = [];

            if ($isFirstPass || count($item_ids) > 10000)
            {
                // continue processing the fields one by one till we reach less than 10000 matches
                // then doing a bulk query is going to be fast as well
                /** @var AbstractField $nextField */
                $nextFieldId = array_keys($fieldList)[0];
                $nextField = $fieldList[$nextFieldId];
                $processFields = [$nextFieldId => $nextField];
                unset($fieldList[$nextFieldId]);
                // finish the loop if there are no more fields left
                $finishLoop = empty($fieldList);
            }
            else
            {
                // process all remaining fields in a bulk query and finish the loop
                $processFields = $fieldList;
                $finishLoop = true;
            }

            $logger->info('Processing fields: ' . json_encode($processFields, JSON_PARTIAL_OUTPUT_ON_ERROR));

            $dsl = $this->getFieldFilterDsl($processFields, $metadata, $sqlFilter);

            if ($dsl === false)
            {
                $logger->debug('No DLS generated, skipping.');
                // there was nothing to match, empty result set should be returned
                if ($finishLoop)
                {
                    break;
                }

                continue;
            }

            $hasElasticConstraints = true;

            try
            {
                $response = $this->es->requestFromIndex('GET', '_search?scroll=10s', $dsl)->getBody();
            }
            catch (Exception $e)
            {
                $logger->error('Error while executing search: ' . $e->getMessage());
                \XF::logError($e->getMessage() . "\nFull request: " . json_encode($dsl, JSON_PRETTY_PRINT));
                $response = [];
            }

            if (!$response || !isset($response['hits']['hits']))
            {
                $logger->debug('Search failed, could be related to missing index, skipping.');

                throw \XF::phrasedException('xfes_search_could_not_be_completed_try_again_later');
            }

            $logger->info('Search response: ' . json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR));

            try
            {
                $this->_aggregateFieldHits($aggregated, $response['hits']['hits']);
            }
            catch (\RuntimeException $e)
            {
                $logger->error('Error while aggregating hits: ' . $e->getMessage());
                \XF::logError(
                    $e->getMessage() . ' for request: '
                    . PHP_EOL
                    . json_encode($dsl, JSON_PRETTY_PRINT)
                );
            }

            // see if we have more to fetch and use scroll API to get the remaining results
            if ($response['hits']['total'] > count($response['hits']['hits']))
            {
                $logger->info('More results to fetch, using scroll API.');
                // make requests to get all remaining entries
                while (true)
                {
                    $response = $this->es->request('GET', '_search/scroll', [
                        'scroll' => '10s',
                        'scroll_id' => $response['_scroll_id'],
                    ])->getBody();

                    if (
                        !$response
                        || !isset($response['hits']['hits'])
                        || empty($response['hits']['hits'])
                    ) {
                        $logger->debug('No more results to fetch, finishing.');

                        break;
                    }

                    $logger->info('Scroll response: ' . json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR));

                    try
                    {
                        $this->_aggregateFieldHits($aggregated, $response['hits']['hits']);
                    }
                    catch (\RuntimeException $e)
                    {
                        $logger->error('Error while aggregating hits: ' . $e->getMessage());
                        \XF::logError(
                            'Invalid ES response (scroll_id): '
                            . PHP_EOL . json_encode($response, JSON_PRETTY_PRINT)
                            . PHP_EOL . ' for request: '
                            . PHP_EOL . json_encode($dsl, JSON_PRETTY_PRINT)
                        );
                    }
                }
            }

            if (empty($aggregated))
            {
                $logger->debug('No aggregated results, skipping.');

                // if there are no results in any pass that would mean we don't have items matching all criteria anyway
                break;
            }

            foreach ($aggregated as $fieldId => $foundIds)
            {
                if ($isFirstPass && empty($item_ids))
                {
                    // if we do not have item ID filter provided as parameter
                    // use the IDs detected for the first field for further filtering
                    $item_ids = array_values($foundIds);
                    $isFirstPass = false;

                    continue;
                }

                // only items matching previous and current criteria match ALL criteria
                $item_ids = array_intersect($item_ids, array_values($foundIds));
                // reset the keys
                $item_ids = array_values($item_ids);

                if ($TEST_ITEM_ID && !in_array($TEST_ITEM_ID, $item_ids))
                {
                    $breakHere = 0;
                }
            }

            // will indicate that we are already in the second loop, so if we have got empty item IDs, then nothing matched
            $isFirstPass = false;

            // store IDs of all fields for which we found any value
            $foundFieldIds = array_merge($foundFieldIds, array_keys($aggregated));

            if ($finishLoop)
            {
                break;
            }
        }

        if ($hasElasticConstraints && empty($item_ids))
        {
            $logger->debug('No items found matching all criteria via ES search.');

            // we have searched via elastic and already no matches
            return [];
        }

        $requiredFieldIds = array_keys($initialFieldList);

        if ($sqlFilter)
        {
            // some fields will be searched using SQL, do not require them in elastic search results
            $requiredFieldIds = array_diff($requiredFieldIds, array_keys($sqlFilter));
        }

        if (array_intersect($requiredFieldIds, $foundFieldIds) !== $requiredFieldIds)
        {
            $logger->debug('Not all required fields were found in elastic search results.');

            // not all fields existing in request found, so no item matches ALL criteria required
            return [];
        }

        // free up memory
        unset($response);

        if ($sqlFilter)
        {
            $logger->info('Searching for items using SQL.');
            // some fields should be searched using sql implementation
            $item_ids = FilterApp::getMySqlSource($this->contentTypeProvider)->search($sqlFilter, $metadata);
        }

        return $item_ids;
    }

    public function getFieldFilterDsl(array $fieldList, array $metadata, &$sqlFilter = [])
    {
        $dsl = [];

        // sorting by _doc is the default and the fastest
        $dsl['sort'] = ['_doc'];

        $fieldIdTermName = 'field_id';

        // field fetching as per XFES
        if ($this->es->majorVersion() >= 5)
        {
            // With XenForo 2.0.9 or earlier the fields are indexed as real keyword field, so no need for the postfix anymore
            // $fieldIdTermName .= '.keyword';
            $dsl['docvalue_fields'] = ['discussion_id', $fieldIdTermName];
            $dsl['_source'] = false;
        }
        else
        {
            $dsl['fields'] = ['discussion_id', $fieldIdTermName];
        }

        // we are going to use scroll API to fetch all documents, make each batch the maximum allowed
        $dsl['size'] = Flags::ES_PAGE_SIZE;

        if ($this->es->isSingleTypeIndex())
        {
            // in single-type index type is stored as a separate keyword
            $dsl['query']['constant_score']['filter']['bool']['must'] = [
                ['term' => ['type' => $this->contentTypeProvider->getIndexContentType()]],
            ];
        }
        else
        {
            // otherwise type is a built-in value in elastic search
            $dsl['query']['constant_score']['filter']['bool']['must'] = [
                ['type' => ['value' => $this->contentTypeProvider->getIndexContentType()]],
            ];
        }

        // shortcut
        $dslCondition = &$dsl['query']['constant_score']['filter']['bool']['must'];

        // let the content provider to add its own conditions based on metadata
        $this->contentTypeProvider->setupElasticDsl($dslCondition, $metadata);

        $fieldValueMatchConditions = [];

        foreach ($fieldList as $fieldId => $field)
        {
            $condition = [
                'must' => [], // all required conditions
                'should' => [], // all optional conditions
            ];
            if ($field instanceof ColorField || $field instanceof LocationField)
            {
                // formula-based filtering is not possible in elastic search (only v 6+ has terms_set filter)
                // use MySQL-based implementation for color and location filtering
                $sqlFilter[$fieldId] = $field;
            }
            elseif (
                ($field instanceof IntField && $fieldName = 'field_int') // assignment in condition
                || ($field instanceof FloatField && $fieldName = 'field_float')
            ) {
                $numericCondition = [];

                switch ($field->getOperator())
                {
                    case 'BETWEEN':
                        $numericCondition = [
                            'range' => [
                                $fieldName => [
                                    'gte' => $field->getValue()[0],
                                    'lte' => $field->getValue()[1],
                                ],
                            ],
                        ];

                        break;

                    case '>=':
                        $numericCondition = [
                            'range' => [
                                $fieldName => [
                                    'gte' => $field->getValue(),
                                ],
                            ],
                        ];

                        break;

                    case '<=':
                        $numericCondition = [
                            'range' => [
                                $fieldName => [
                                    'lte' => $field->getValue(),
                                ],
                            ],
                        ];

                        break;

                    default: // =
                        $numericCondition = [
                            'term' => [
                                $fieldName => $field->getValue(),
                            ],
                        ];

                        break;
                }
                $condition['must'][] = $numericCondition;
            }
            elseif ($field instanceof FreeTextField)
            {
                $condition['must'][] = [
                    'simple_query_string' => [
                        'query' => \XF::app()->search()->getParsedKeywords($field->getAsString()),
                        'fields' => ['message'],
                        'default_operator' => 'and',
                    ],
                ];
            }
            elseif (
                $field instanceof SingleChoiceField
            ) {
                if ($field->isExactMatch())
                {
                    $condition['must'][] = [
                        'terms' => [
                            'field_choice_single' => $field->getChoices(),
                        ],
                    ];
                }
                else
                {
                    foreach ($field->getChoices() as $singleValue)
                    {
                        $condition['should'][] = [
                            'wildcard' => [
                                'field_choice_single' => $singleValue . '*',
                            ],
                        ];
                    }
                }
            }
            elseif ($field instanceof MultipleChoiceField)
            {
                $conditionKey = $field->getMatchType() === 'OR' ? 'should' : 'must';
                foreach ($field->getChoices() as $singleValue)
                {
                    $condition[$conditionKey][] = [
                        'wildcard' => [
                            'field_choice_multiple' => '*' . TypeProvider::$choiceSeparator . $singleValue . TypeProvider::$choiceSeparator . '*',
                        ],
                    ];
                }
            }
            else
            {
                throw new \RuntimeException('Unhandled field type - ' . get_class($field));
            }

            if ($condition['must'] || $condition['should'])
            {
                // field_id=$fieldId
                $condition['must'][] = [
                    'term' => [
                        $fieldIdTermName => $fieldId,
                    ],
                ];
                // AND (optional1 OR optional2)
                if (!empty($condition['should']))
                {
                    $condition['must'][] = [
                        'bool' => [
                            'should' => $condition['should'],
                        ],
                    ];
                }

                $fieldValueMatchConditions[] = [
                    'bool' => [
                        'must' => $condition['must'],
                    ],
                ];
            }
        }

        if (empty($fieldValueMatchConditions))
        {
            return false; // no particular values to match in the index, no search
        }

        $dslCondition[] = [
            'bool' => [
                'should' => $fieldValueMatchConditions,
            ],
        ];

        return $dsl;
    }

    /**
     * @param mixed $hits
     *
     * @return array
     */
    protected function _aggregateFieldHits(array &$aggregated, $hits)
    {
        foreach ($hits as $hit)
        {
            $fieldId = null;
            if (isset($hit['fields']['field_id']))
            {
                $fieldId = $hit['fields']['field_id'][0];
            }
            elseif (isset($hit['fields']['field_id.keyword']))
            {
                $fieldId = $hit['fields']['field_id.keyword'][0];
            }

            if ($fieldId === null)
            {
                throw new \RuntimeException('Invalid ES response');
            }

            $itemId = $hit['fields']['discussion_id'][0];

            $aggregated[$fieldId][$itemId] = $itemId;
        }

        return $aggregated;
    }
}

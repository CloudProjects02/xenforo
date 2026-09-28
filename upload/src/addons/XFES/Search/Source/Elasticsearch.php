<?php

namespace XFES\Search\Source;

use XF\PrintableException;
use XF\Search\IndexRecord;
use XF\Search\Query;
use XF\Search\Source\AbstractSource;
use XFES\Elasticsearch\Api;
use XFES\Elasticsearch\Exception as EsException;
use XFES\Repository\IndexFailed;
use XFES\Search\Query\FunctionOrder;
use XFES\Search\Query\MoreLikeThisQuery;
use XFES\Service\Optimizer;
use XFES\XF\Search\Search;

use function array_slice, count, intval, is_array, strlen;

class Elasticsearch extends AbstractSource
{
	/**
	 * @var Api
	 */
	protected $es;

	protected $bulkIndexRecords = [];

	public function __construct(Api $es)
	{
		$this->es = $es;
	}

	public function isRelevanceSupported()
	{
		return true;
	}

	public function isAutoCompleteSupported(): bool
	{
		return true;
	}

	public function index(IndexRecord $record)
	{
		$type = $record->type;
		$id = $record->id;

		$data = $this->getDocument($record);

		if ($this->bulkIndexing)
		{
			$this->bulkIndexRecords[] = [
				'type' => $type,
				'id' => $id,
				'data' => $data,
			];
			if (count($this->bulkIndexRecords) >= 500)
			{
				$this->flushBulkIndexing();
			}

			return true;
		}
		else
		{
			try
			{
				$this->es->index($type, $id, $data);
				return true;
			}
			catch (EsException $e)
			{
				$this->logFailedIndexing($type, $id, $data);
				$this->logElasticsearchException($e, "Elasticsearch indexing error (queued): ");
				return false;
			}
		}
	}

	protected function flushBulkIndexing()
	{
		try
		{
			$this->es->indexBulk($this->bulkIndexRecords);
		}
		catch (EsException $e)
		{
			$this->logElasticsearchException($e, "Elasticsearch indexing error: ");
			throw new PrintableException(
				\XF::phrase('xfes_error_was_triggered_while_indexing_see_log', [
					'link' => \XF::app()->router('admin')->buildLink('logs/server-errors'),
				])
			);
		}

		$this->bulkIndexRecords = [];
	}

	public function delete($type, $ids)
	{
		try
		{
			if (is_array($ids))
			{
				$this->es->deleteBulk($type, $ids);
			}
			else
			{
				$this->es->delete($type, $ids);
			}

			return true;
		}
		catch (EsException $e)
		{
			if (is_array($ids))
			{
				foreach ($ids AS $id)
				{
					$this->logFailedIndexing($type, $id);
				}
			}
			else
			{
				$this->logFailedIndexing($type, $ids);
			}

			$this->logElasticsearchException($e, "Elasticsearch indexing error (queued): ");

			return false;
		}
	}

	public function truncate($type = null)
	{
		if ($type)
		{
			// ES can't delete by type. It can delete by query but this could potentially delete millions of documents
			// which is likely to be problematic/slow.
		}
		else
		{
			/** @var Optimizer $optimizer */
			$optimizer = \XF::app()->service('XFES:Optimizer', $this->es);

			/** @var \XFES\Service\Analyzer $analyzer */
			$analyzer = \XF::app()->service('XFES:Analyzer', $this->es);
			$settings = $analyzer->getAnalyzerFromConfig($analyzer->getCurrentConfig());

			$optimizer->optimize($settings, true);
		}
	}

	/**
	 * @param Query\KeywordQuery $query
	 * @param int                $maxResults
	 *
	 * @return array
	 */
	public function search(Query\KeywordQuery $query, $maxResults)
	{
		return $this->executeSearch(
			$query,
			$this->getKeywordSearchDsl($query, $maxResults),
			$maxResults
		);
	}

	/**
	 * @param Query\KeywordQuery $query
	 * @param int                $maxResults
	 *
	 * @return array
	 */
	public function getKeywordSearchDsl(
		Query\KeywordQuery $query,
		$maxResults
	)
	{
		$dsl = $this->getCommonSearchDsl($query, $maxResults);

		$filters = [];
		$filtersNot = [];

		$queryDsl = $this->getKeywordSearchQueryDsl(
			$query,
			$filters,
			$filtersNot
		);
		$this->applyDslFilters($query, $filters, $filtersNot);

		$queryDsl = $this->getSearchQueryFunctionScoreDsl($query, $queryDsl);
		$queryDsl = $this->getSearchQueryBoolDsl(
			$queryDsl,
			$filters,
			$filtersNot
		);

		$dsl['query'] = $queryDsl ?: ['match_all' => (object) []];

		return $dsl;
	}

	/**
	 * @param Query\KeywordQuery $query
	 * @param array              $filters
	 * @param array              $filtersNot
	 *
	 * @return array
	 */
	protected function getKeywordSearchQueryDsl(
		Query\KeywordQuery $query,
		array &$filters,
		array &$filtersNot
	)
	{
		$keywords = $query->getParsedKeywords();
		if (!$keywords)
		{
			return [];
		}

		return [
			'simple_query_string' => [
				'query' => $keywords,
				'fields' => $query->getTitleOnly()
					? ['title']
					: ['title', 'message'],
				'default_operator' => 'and',
			],
		];
	}

	/**
	 * @return array
	 */
	public function autoComplete(
		Query\KeywordQuery $query,
		int $maxResults
	): array
	{
		return $this->executeSearch(
			$query,
			$this->getKeywordAutoCompleteDsl($query, $maxResults),
			$maxResults
		);
	}

	/**
	 * @return array
	 */
	public function getKeywordAutoCompleteDsl(
		Query\KeywordQuery $query,
		int $maxResults
	): array
	{
		$dsl = $this->getCommonSearchDsl($query, $maxResults);

		$filters = [];
		$filtersNot = [];

		$queryDsl = $this->getKeywordAutoCompleteQueryDsl(
			$query,
			$filters,
			$filtersNot
		);
		$this->applyDslFilters($query, $filters, $filtersNot);

		$queryDsl = $this->getSearchQueryFunctionScoreDsl($query, $queryDsl);
		$queryDsl = $this->getSearchQueryBoolDsl(
			$queryDsl,
			$filters,
			$filtersNot
		);

		$dsl['query'] = $queryDsl ?: ['match_all' => (object) []];

		return $dsl;
	}

	/**
	 * @param array $filters
	 * @param array $filtersNot
	 *
	 * @return array
	 */
	protected function getKeywordAutoCompleteQueryDsl(
		Query\KeywordQuery $query,
		array &$filters,
		array &$filtersNot
	): array
	{
		$keywords = $query->getParsedKeywords();
		if (!$keywords)
		{
			return [];
		}

		/** @var Search $searcher */
		$searcher = \XF::app()->search();
		$autoCompletableTypes = $searcher->getAutoCompletableTypes();
		if (!$autoCompletableTypes)
		{
			return [];
		}

		$types = $query->getTypes();
		if (!$types)
		{
			$this->applyMetadataConstraint(
				new Query\MetadataConstraint('type', $autoCompletableTypes),
				$filters,
				$filtersNot
			);
		}

		return [
			'multi_match' => [
				'type' => 'bool_prefix',
				'query' => $keywords,
				'fields' => [
					'title_suggest',
					'title_suggest._2gram',
					'title_suggest._3gram',
				],
				'minimum_should_match' => '2<80%',
				'fuzziness' => 'AUTO',
				'prefix_length' => 1,
			],
		];
	}

	/**
	 * @param MoreLikeThisQuery $query
	 * @param int                                  $maxResults
	 *
	 * @return array
	 */
	public function moreLikeThis(
		MoreLikeThisQuery $query,
		$maxResults
	)
	{
		return $this->executeSearch(
			$query,
			$this->getMLTSearchDsl($query, $maxResults),
			$maxResults
		);
	}

	/**
	 * @param MoreLikeThisQuery $query
	 * @param int                                  $maxResults
	 *
	 * @return array
	 */
	public function getMLTSearchDsl(
		MoreLikeThisQuery $query,
		$maxResults
	)
	{
		$dsl = $this->getCommonSearchDsl($query, $maxResults);

		$filters = [];
		$filtersNot = [];

		$queryDsl = $this->getMLTSearchQueryDsl(
			$query,
			$filters,
			$filtersNot
		);
		$this->applyDslFilters($query, $filters, $filtersNot);

		$queryDsl = $this->getSearchQueryFunctionScoreDsl($query, $queryDsl);
		$queryDsl = $this->getSearchQueryBoolDsl(
			$queryDsl,
			$filters,
			$filtersNot
		);

		$dsl['query'] = $queryDsl ?: ['match_all' => (object) []];

		return $dsl;
	}

	/**
	 * @param MoreLikeThisQuery $query
	 * @param array                                $filters
	 * @param array                                $filtersNot
	 *
	 * @return array
	 */
	protected function getMLTSearchQueryDsl(
		MoreLikeThisQuery $query,
		array &$filters,
		array &$filtersNot
	)
	{
		$entities = $query->getEntities();
		if (!$entities)
		{
			return [];
		}

		$documents = [];
		$ids = [];
		$searcher = \XF::app()->search();
		$stringFormatter = \XF::app()->stringFormatter();
		foreach ($entities AS $entity)
		{
			$handler = $searcher->handler($entity->getEntityContentType());
			$record = $handler->getIndexData($entity);
			if (!$record)
			{
				continue;
			}

			$doc = $this->getDocument($record);
			$doc['message'] = trim($stringFormatter->stripBbCode($doc['message'])) . ' ' . $doc['title'];
			// including the title in the message helps search additional keywords

			$documents[] = ['doc' => $doc];

			if ($entity->exists())
			{
				$type = $record->type;
				$id = $record->id;
				$ids[] = "{$type}-{$id}";
			}
		}

		if (!$documents)
		{
			return ['match_none' => (object) []];
		}

		if ($ids)
		{
			$filtersNot[] = ['ids' => ['values' => $ids]];
		}

		return [
			'more_like_this' => [
				'fields' => ['title', 'message'],
				'like' => $documents,

				'min_term_freq' => 1,
				'min_doc_freq' => 3,
				'boost_terms' => 1,
			],
		];
	}

	/**
	 * @param Query\Query $query
	 * @param int         $maxResults
	 *
	 * @return array
	 */
	protected function getCommonSearchDsl(Query\Query $query, $maxResults)
	{
		$dsl = [];

		$dsl['sort'] = $this->getSearchSortDsl($query);

		// TODO: use aggregations for grouping if no query constraints?
		// TODO: don't pull any fields if we don't need them
		// fields is no longer accessible. stored_fields only works if explicitly stored. _source
		// only works if it hasn't been removed. docvalue_fields works consistently.
		$dsl['docvalue_fields'] = [
			['field' => 'discussion_id'],
			['field' => 'user'],
			['field' => 'date'],
		];

		$dsl['_source'] = false;

		$dsl['size'] = $this->getSearchSizeDsl($query, $maxResults);

		return $dsl;
	}

	/**
	 * @param Query\Query $query
	 *
	 * @return array
	 */
	protected function getSearchSortDsl(Query\Query $query)
	{
		$order = $query->getOrder();
		if (
			$order == 'relevance'
			|| $order instanceof FunctionOrder
		)
		{
			return [
				'_score',
				['date' => 'desc'],
			];
		}

		return [
			['date' => 'desc'],
		];
	}

	/**
	 * @param Query\Query $query
	 * @param int         $maxResults
	 *
	 * @return int
	 */
	protected function getSearchSizeDsl(Query\Query $query, $maxResults)
	{
		$fetchResults = $maxResults;

		if ($query->hasQueryConstraints())
		{
			$fetchResults *= 4;
		}

		if ($query->getGroupByType())
		{
			$fetchResults *= 4;
		}

		return min(10000, $fetchResults);
	}

	/**
	 * @param Query\Query $query
	 * @param array       $filters
	 * @param array       $filtersNot
	 */
	protected function applyDslFilters(
		Query\Query $query,
		array &$filters,
		array &$filtersNot
	)
	{
		$dateRange = [];
		if ($query->getMinDate())
		{
			$dateRange['gt'] = $query->getMinDate();
		}
		if ($query->getMaxDate())
		{
			$dateRange['lt'] = $query->getMaxDate();
		}
		if ($dateRange)
		{
			$filters[] = [
				'range' => ['date' => $dateRange],
			];
		}

		if (!$query->getAllowHidden())
		{
			$filtersNot[] = [
				'exists' => ['field' => 'hidden'],
			];
		}

		$userIds = $query->getUserIds();
		if ($userIds)
		{
			$this->applyMetadataConstraint(
				new Query\MetadataConstraint('user', $userIds),
				$filters,
				$filtersNot
			);
		}

		$types = $query->getTypes();
		if ($types)
		{
			$this->applyTypeMetadataConstraint(
				new Query\TypeMetadataConstraint($types),
				$filters,
				$filtersNot
			);
		}

		foreach ($query->getMetadataConstraints() AS $metadataConstraint)
		{
			$this->applyMetadataConstraint(
				$metadataConstraint,
				$filters,
				$filtersNot
			);
		}

		foreach ($query->getTypeMetadataConstraints() AS $metadataConstraint)
		{
			$this->applyTypeMetadataConstraint(
				$metadataConstraint,
				$filters,
				$filtersNot
			);
		}

		foreach ($query->getPermissionConstraints() AS $permissionConstraints)
		{
			foreach ($permissionConstraints['constraints'] AS $permissionConstraint)
			{
				$this->applyMetadataConstraint(
					$permissionConstraint,
					$filters,
					$filtersNot
				);
			}
		}

		foreach ($query->getPermissionTypeConstraints() AS $permissionConstraints)
		{
			foreach ($permissionConstraints['constraints'] AS $permissionConstraint)
			{
				$this->applyTypeMetadataConstraint(
					$permissionConstraint,
					$filters,
					$filtersNot
				);
			}
		}
	}

	protected function applyMetadataConstraint(Query\MetadataConstraint $metadata, array &$filters, array &$filtersNot)
	{
		$key = $metadata->getKey();
		$values = $metadata->getValues();
		if (!$values)
		{
			return;
		}

		switch ($metadata->getMatchType())
		{
			case Query\MetadataConstraint::MATCH_ANY:
				if (count($values) > 1)
				{
					$filters[] = [
						'terms' => [$key => $values],
					];
				}
				else
				{
					$filters[] = [
						'term' => [$key => reset($values)],
					];
				}
				break;

			case Query\MetadataConstraint::MATCH_ALL:
				if (count($values) > 1)
				{
					$subBools = [];
					foreach ($values AS $value)
					{
						$subBools[] = [
							'term' => [
								$key => $value,
							],
						];
					}
					$filters[] = [
						'bool' => ['filter' => $subBools],
					];
				}
				else
				{
					$filters[] = [
						'term' => [$key => reset($values)],
					];
				}
				break;

			case Query\MetadataConstraint::MATCH_NONE:
				if (count($values) > 1)
				{
					$filtersNot[] = [
						'terms' => [$key => $values],
					];
				}
				else
				{
					$filtersNot[] = [
						'term' => [$key => reset($values)],
					];
				}
				break;
		}
	}

	protected function applyTypeMetadataConstraint(
		Query\TypeMetadataConstraint $metadata,
		array &$filters,
		array &$filtersNot
	): void
	{
		$metadataConstraints = $metadata->getMetadataConstraints();
		if (!$metadataConstraints)
		{
			$typeConstraint = new Query\MetadataConstraint(
				'type',
				$metadata->getTypes(),
				$metadata->getMatchType() === Query\TypeMetadataConstraint::MATCH_ANY
					? Query\MetadataConstraint::MATCH_ANY
					: Query\MetadataConstraint::MATCH_NONE
			);
			$this->applyMetadataConstraint(
				$typeConstraint,
				$filters,
				$filtersNot
			);
			return;
		}

		$subFilters = [];
		$subFiltersNot = [];
		foreach ($metadataConstraints AS $metadataConstraint)
		{
			$this->applyMetadataConstraint(
				$metadataConstraint,
				$subFilters,
				$subFiltersNot
			);
		}

		$filtersNot[] = [
			'bool' => [
				'filter' => [
					'terms' => [
						'type' => $metadata->getTypes(),
					],
				],
				'must_not' => [
					'bool' => [
						'filter' => $subFilters,
						'must_not' => $subFiltersNot,
					],
				],
			],
		];
	}

	/**
	 * @param Query\Query $query
	 * @param array       $queryDsl
	 *
	 * @return array
	 */
	protected function getSearchQueryFunctionScoreDsl(
		Query\Query $query,
		array $queryDsl
	)
	{
		if (!$queryDsl)
		{
			return $queryDsl;
		}

		$order = $query->getOrder();

		if ($order != 'relevance' && !($order instanceof FunctionOrder))
		{
			return $queryDsl;
		}

		$functions = [];

		$orderByRelevance = (
			$order == 'relevance'
			|| (
				$order instanceof FunctionOrder
				&& $order->getIncludeDefaultWeighting()
			)
		);
		$recencyRelevance = \XF::options()->xfesRecencyRelevance;
		if ($orderByRelevance && $recencyRelevance['enabled'])
		{
			$functions[] = [
				'exp' => [
					'date' => [
						'origin' => \XF::$time,
						'decay' => 0.5,
						'scale' => 86400 * max(1, $recencyRelevance['halfLife']),
					],
				],
			];
		}

		if ($order instanceof FunctionOrder)
		{
			foreach ($order->getFunctions() AS $function)
			{
				if (is_array($function))
				{
					$functions[] = $function;
				}
				else if ($function instanceof \Closure)
				{
					$functions[] = $function($query, $this->es);
				}
			}
		}

		return [
			'function_score' => [
				'query' => $queryDsl,
				'functions' => $functions,
			],
		];
	}

	/**
	 * @param array $queryDsl
	 * @param array $filters
	 * @param array $filtersNot
	 *
	 * @return array
	 */
	protected function getSearchQueryBoolDsl(
		array $queryDsl,
		array $filters,
		array $filtersNot
	)
	{
		if (!$filters && !$filtersNot)
		{
			return $queryDsl;
		}

		$bool = [];

		if ($filters)
		{
			$bool['filter'] = $filters;
		}

		if ($filtersNot)
		{
			$bool['must_not'] = $filtersNot;
		}

		if ($queryDsl)
		{
			$bool['must'] = [$queryDsl];
		}

		return ['bool' => $bool];
	}

	/**
	 * Process the raw Elasticsearch response before standard hit processing.
	 *
	 * This method is called after receiving the response but before validating
	 * and processing hits. Override this to access aggregations, scoring details,
	 * or other response data not normally exposed.
	 *
	 * @param Query\Query  $query
	 * @param array|null   $response
	 * @param array        $dsl
	 */
	protected function processResponse(
		Query\Query $query,
		?array &$response,
		array $dsl
	): void
	{
	}

	/**
	 * @param Query\Query $query
	 * @param array       $dsl
	 * @param int         $maxResults
	 *
	 * @return array
	 *
	 * @throws PrintableException
	 */
	protected function executeSearch(Query\Query $query, array $dsl, $maxResults)
	{
		try
		{
			$response = $this->es->search($dsl);
		}
		catch (EsException $e)
		{
			$this->logElasticsearchException($e);
			$response = null;
		}

		$this->processResponse($query, $response, $dsl);

		if (!$response || !isset($response['hits']['hits']))
		{
			throw \XF::phrasedException(
				'xfes_search_could_not_be_completed_try_again_later'
			);
		}

		$hits = $response['hits']['hits'];

		if ($query->hasQueryConstraints())
		{
			$matches = $this->getSqlResults($hits, $query, $maxResults);
		}
		else
		{
			$matches = [];
			$groupType = $query->getGroupByType();
			foreach ($hits AS $hit)
			{
				if ($groupType)
				{
					$discussionId = $hit['fields']['discussion_id'][0];
					$matches[$discussionId] = [
						'content_type' => $groupType,
						'content_id' => $discussionId,
					];
				}
				else
				{
					$typeAndId = $this->es->getTypeAndIdFromHit($hit);
					$matches[] = [
						'content_type' => $typeAndId[0],
						'content_id' => intval($typeAndId[1]),
					];
				}
			}
		}

		return array_slice($matches, 0, $maxResults);
	}

	/**
	 * @param array       $hits
	 * @param Query\Query $query
	 * @param int         $maxResults
	 *
	 * @return array
	 */
	protected function getSqlResults(
		array $hits,
		Query\Query $query,
		$maxResults
	)
	{
		$db = \XF::db();

		$db->query('DROP TABLE IF EXISTS xf_search_index_temp');

		$db->query('
			CREATE TEMPORARY TABLE xf_search_index_temp (
				hit_position int unsigned not null PRIMARY KEY,
				content_type varbinary(25) not null,
				content_id int unsigned not null,
				user_id int unsigned not null,
				item_date int unsigned not null,
				discussion_id int unsigned not null
			)
		');
		$bulkInsert = [];
		$bulkInsertLength = 0;
		$insertQuery = '
			INSERT INTO xf_search_index_temp
				(hit_position, content_type, content_id, user_id, item_date, discussion_id)
			VALUES
				%s
		';
		$hitPosition = 1;

		foreach ($hits AS $hit)
		{
			$typeAndId = $this->es->getTypeAndIdFromHit($hit);
			$fields = $hit['fields'];

			$row = '(' . $db->quote($hitPosition)
				. ', ' . $db->quote($typeAndId[0])
				. ', ' . $db->quote($typeAndId[1])
				. ', ' . $db->quote($fields['user'][0])
				. ', ' . $db->quote($fields['date'][0])
				. ', ' . $db->quote($fields['discussion_id'][0]) . ')';

			$bulkInsert[] = $row;
			$bulkInsertLength += strlen($row);

			if ($bulkInsertLength > 500000)
			{
				$db->query(sprintf($insertQuery, implode(', ', $bulkInsert)));

				$bulkInsert = [];
				$bulkInsertLength = 0;
			}

			$hitPosition++;
		}

		if ($bulkInsert)
		{
			$db->query(sprintf($insertQuery, implode(',', $bulkInsert)));
		}

		/** @var Query\TableReference[] $tables */
		$tables = [];
		$where = [];

		foreach ($query->getSqlConstraints() AS $constraint)
		{
			$where[] = $constraint->getSql($db);
			$tables += $constraint->getTables();
		}

		$order = $query->getOrder();
		if ($order instanceof Query\SqlOrder)
		{
			$tables += $order->getTables();
			$orderByClause = 'ORDER BY ' . $order->getOrder() . ', search_index.hit_position ASC';
		}
		else
		{
			$orderByClause = 'ORDER BY search_index.hit_position ASC';
		}

		$joins = '';
		foreach ($tables AS $table)
		{
			$joins .= "INNER JOIN " . $table->getTable() . " AS " . $table->getAlias() . " ON (" . $table->getCondition() . ")\n";
		}

		if ($where)
		{
			$whereClause = 'WHERE ' . implode(' AND ', $where);
		}
		else
		{
			$whereClause = '';
		}

		$groupType = $query->getGroupByType();
		if ($groupType)
		{
			$selectFields = $db->quote($groupType) . ' AS content_type, search_index.discussion_id AS content_id';
			$groupByClause = 'GROUP BY search_index.discussion_id';
		}
		else
		{
			$selectFields = 'search_index.content_type, search_index.content_id';
			$groupByClause = '';
		}

		$maxResults = intval($maxResults);
		if ($maxResults <= 0)
		{
			$maxResults = 1;
		}

		$results = $db->fetchAllNum("
			SELECT {$selectFields}
			FROM xf_search_index_temp AS search_index
			{$joins}
			{$whereClause}
			{$groupByClause}
			{$orderByClause}
			LIMIT {$maxResults}
		");

		return $results;
	}

	/**
	 * @return Api
	 */
	public function getEsApi(): Api
	{
		return $this->es;
	}

	public function getWordSplitRange()
	{
		return '\x00-\x21\x23-\x26\x28\x29\x2B\x2C\x2F\x3A-\x40\x5B-\x5E\x60\x7B-\x7F';
	}

	public function getMaxKeywords(): int
	{
		return $this->getMaxClauseCount();
	}

	protected function getMaxClauseCount(): int
	{
		$options = \XF::options();
		$xfesConfig = $options->xfesConfig;

		if (!isset($xfesConfig['maxClauseCount'])
			|| !isset($xfesConfig['maxClauseCountChecked'])
			|| !$xfesConfig['maxClauseCountChecked']
			|| $xfesConfig['maxClauseCountChecked'] < \XF::$time - 60 * 60 * 6 // 6 hours
		)
		{
			$clusterSettings = $this->es->getClusterSettings();

			$maxClauseCount = $clusterSettings['defaults']['indices']['query']['bool']['max_clause_count']
				?? $clusterSettings['defaults']['index']['query']['bool']['max_clause_count']
				?? null;

			if ($maxClauseCount === null)
			{
				$maxClauseCount = self::DEFAULT_MAX_KEYWORDS;
			}

			$xfesConfig['maxClauseCount'] = $maxClauseCount;
			$xfesConfig['maxClauseCountChecked'] = \XF::$time;

			\XF::repository('XF:Option')->updateOption('xfesConfig', $xfesConfig);
		}

		return intval($xfesConfig['maxClauseCount']);
	}

	protected function finalizeParsedKeywords(array $parsed)
	{
		$query = '';
		foreach ($parsed AS $part)
		{
			if ($part[0] == '|')
			{
				$part[0] = '| ';
			}

			$query .= " $part[0]$part[1]";
		}

		return trim($query);
	}

	/**
	 * @param IndexRecord $record
	 *
	 * @return array
	 */
	protected function getDocument(IndexRecord $record)
	{
		$document = [
			'title' => $record->title,
			'message' => $record->message,
			'date' => $record->date,
			'user' => $record->userId,
			'discussion_id' => $record->discussionId,
		];

		$suggestEnabled = \XF::options()->searchSuggestions['enabled'];
		if ($suggestEnabled)
		{
			$document['title_suggest'] = $record->originalTitle;
		}

		$document += $record->metadata;

		if ($record->hidden)
		{
			$document['hidden'] = true;
		}

		return $document;
	}

	protected function logElasticsearchException(EsException $e, $errorPrefix = "Elasticsearch error: ")
	{
		\XF::logException($e, false, $errorPrefix);
	}

	protected function logFailedIndexing($type, $id, ?array $record = null)
	{
		/** @var IndexFailed $indexFailed */
		$indexFailed = \XF::repository('XFES:IndexFailed');
		$indexFailed->logFailedIndexing($type, $id, $record);
	}
}

<?php

namespace XF\Api\Controller;

use XF\ControllerPlugin\SearchPlugin;
use XF\Entity\Search;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\SearchRepository;
use XF\Search\Query\KeywordQuery;

/**
 * @api-group Search
 */
class SearchController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (
			!\XF::isApiBypassingPermissions()
			&& !\XF::visitor()->canSearch($error)
		)
		{
			throw $this->exception($this->noPermission($error));
		}

		$this->assertApiScopeByRequestMethod('search');
	}

	/**
	 * @api-desc Retrieves older search results for a given search
	 *
	 * @api-in <req> int $search_id
	 * @api-in int $before
	 *
	 * @api-out true $success
	 * @api-out Search $search
	 */
	public function actionPostOlder(ParameterBag $params): AbstractReply
	{
		$search = $this->em()->find(Search::class, $params->search_id);
		if (!$search || $search->user_id !== \XF::visitor()->user_id)
		{
			return $this->notFound();
		}

		$searchPlugin = $this->plugin(SearchPlugin::class);

		$input = $searchPlugin->convertSearchToQueryInput($search);

		$before = $this->filter('before', 'uint');
		if ($before)
		{
			$input['c']['older_than'] = $before;
		}

		$query = $searchPlugin->prepareSearchQuery($input, $constraints);
		$searchPlugin->assertValidSearchQuery($query);

		return $this->runSearch($query, $constraints);
	}

	/**
	 * @api-desc Retrieves search results for a given search
	 *
	 * @api-in int $page
	 *
	 * @api-out Search $search
	 * @api-out array $results
	 * @api-out pagination $pagination
	 * @api-out int|null $get_older_results_date
	 */
	public function actionGet(ParameterBag $params): AbstractReply
	{
		$search = $this->em()->find(Search::class, $params->search_id);
		$visitor = \XF::visitor();
		if (!$search || $visitor->user_id !== $search->user_id)
		{
			return $this->notFound();
		}

		$page = $this->filterPage();
		$perPage = $this->options()->searchResultsPerPage;
		$maxPage = ceil($search->result_count / $perPage);

		$this->assertValidApiPage($page, $perPage, $search->result_count);

		$searcher = $this->app()->search();
		$resultSet = $searcher->getResultSet($search->search_results);
		$resultSet->sliceResultsToPage($page, $perPage);
		if (!$resultSet->countResults())
		{
			return $this->message(\XF::phrase('no_results_found'));
		}

		if (
			$search->search_order === 'date'
			&& $search->result_count > $perPage
			&& $page === $maxPage
		)
		{
			$lastResult = $resultSet->getLastResultData($lastResultType);
			$lastResultHandler = $searcher->handler($lastResultType);
			$getOlderResultsDate = $lastResultHandler->getResultDate($lastResult);
		}
		else
		{
			$getOlderResultsDate = null;
		}

		$results = $searcher->getResultSetApiResults(
			$resultSet,
			Entity::VERBOSITY_VERBOSE
		);

		$pagination = $this->getPaginationData(
			$results,
			$page,
			$perPage,
			$search->result_count
		);

		return $this->apiSuccess([
			'search' => $search->toApiResult(Entity::VERBOSITY_VERBOSE),
			'results' => $results,
			'pagination' => $pagination,
			'get_older_results_date' => $getOlderResultsDate,
		]);
	}

	protected function runSearch(
		KeywordQuery $query,
		array $constraints,
		bool $allowCached = true
	): AbstractReply
	{
		$searchRepo = $this->repository(SearchRepository::class);
		$search = $searchRepo->runSearch($query, $constraints, $allowCached);

		if (!$search)
		{
			return $this->message(\XF::phrase('no_results_found'));
		}

		return $this->apiSuccess([
			'search' => $search->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}
}

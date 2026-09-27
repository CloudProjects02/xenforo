<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\SearchPlugin;
use XF\Entity\Search;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\SearchRepository;
use XF\Search\Query\KeywordQuery;

class SearchController extends AbstractController
{
	/**
	 * @var int
	 */
	protected const MAX_AUTO_COMPLETE_RESULTS = 8;

	protected function preDispatchController($action, ParameterBag $params)
	{
		$visitor = \XF::visitor();
		if (!$visitor->canSearch($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(ParameterBag $params)
	{
		if ($params->search_id && !$this->filter('searchform', 'bool'))
		{
			return $this->rerouteController(self::class, 'results', $params);
		}

		$this->assertNotEmbeddedImageRequest();

		$input = $this->convertShortSearchInputNames();
		$input = $this->mergeInputFromSearchMenu($input);

		$searcher = $this->app->search();
		$type = $input['search_type'] ?? $this->filter('type', 'str');

		$viewParams = [
			'tabs' => $searcher->getSearchTypeTabs(),
			'type' => $type,
			'isRelevanceSupported' => $searcher->isRelevanceSupported(),
			'input' => $input,
		];

		$typeHandler = $type && $searcher->isValidContentType($type)
			? $searcher->handler($type)
			: null;
		if ($typeHandler && $typeHandler->getSearchFormTab())
		{
			$viewParams = array_merge(
				$viewParams,
				$typeHandler->getSearchFormData()
			);
			$templateName = $typeHandler->getTypeFormTemplate();

			$sectionContext = $typeHandler->getSectionContext();
			if ($sectionContext)
			{
				$this->setSectionContext($sectionContext);
			}
		}
		else
		{
			$viewParams['type'] = '';
			$templateName = 'search_form_all';
		}

		$viewParams['formTemplateName'] = $templateName;

		return $this->view('XF:Search\Form', 'search_form', $viewParams);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionSearch()
	{
		if ($this->request->exists('from_search_menu'))
		{
			return $this->rerouteController(self::class, 'index');
		}

		$this->assertNotEmbeddedImageRequest();

		$input = $this->getSearchInput();

		$query = $this->prepareSearchQuery($input, $constraints);

		$searchPlugin = $this->plugin(SearchPlugin::class);
		$searchPlugin->assertValidSearchQuery($query);

		return $this->runSearch($query, $constraints);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionOlder(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		$search = $this->em()->find(Search::class, $params->search_id);
		if (!$search || $search->user_id !== \XF::visitor()->user_id)
		{
			return $this->notFound();
		}

		$input = $this->convertSearchToQueryInput($search);

		$before = $this->filter('before', 'uint');
		if ($before)
		{
			$input['c']['older_than'] = $before;
		}

		$query = $this->prepareSearchQuery($input, $constraints);

		$searchPlugin = $this->plugin(SearchPlugin::class);
		$searchPlugin->assertValidSearchQuery($query);

		return $this->runSearch($query, $constraints);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionMember()
	{
		$this->assertNotEmbeddedImageRequest();

		$userId = $this->filter('user_id', 'uint');
		$user = $this->assertRecordExists(
			User::class,
			$userId,
			null,
			'requested_member_not_found'
		);

		$input = [
			'c' => [
				'users' => $user->username,
			],
			'order' => 'date',
		];

		$content = $this->filter('content', 'str');
		if ($content)
		{
			$input['search_type'] = $content;
		}

		$type = $this->filter('type', 'str');
		if ($type)
		{
			$input['c']['type'] = $type;
		}

		$before = $this->filter('before', 'uint');
		if ($before)
		{
			$input['c']['older_than'] = $before;
		}

		$threadType = $this->filter('thread_type', 'str');
		if ($threadType)
		{
			$input['c']['thread_type'] = $threadType;
		}

		$grouped = $this->filter('grouped', 'bool');
		if ($grouped)
		{
			$input['grouped'] = 1;
		}

		$query = $this->prepareSearchQuery($input, $constraints);

		$searchPlugin = $this->plugin(SearchPlugin::class);
		$searchPlugin->assertValidSearchQuery($query);

		return $this->runSearch($query, $constraints);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionResults(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		$search = $this->em()->find(Search::class, $params->search_id);
		$visitor = \XF::visitor();
		if (!$search || $visitor->user_id !== $search->user_id)
		{
			$searchData = $this->convertShortSearchInputNames();
			$query = $this->prepareSearchQuery($searchData, $constraints);

			$searchPlugin = $this->plugin(SearchPlugin::class);
			$searchPlugin->assertValidSearchQuery($query);

			return $this->runSearch($query, $constraints);
		}

		$page = $this->filterPage();
		$perPage = $this->options()->searchResultsPerPage;
		$maxPage = (int) ceil($search->result_count / $perPage);

		$this->assertValidPage(
			$page,
			$perPage,
			$search->result_count,
			'search',
			$search
		);

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

		$resultOptions = [
			'search' => $search,
			'term' => $search->search_query,
		];
		$results = $searcher->wrapResultsForRender($resultSet, $resultOptions);

		$modTypes = [];
		foreach ($results AS $result)
		{
			$handler = $result->getHandler();
			$entity = $result->getResult();
			if (!$handler->canUseInlineModeration($entity))
			{
				continue;
			}

			$type = $handler->getContentType();
			if (isset($modTypes[$type]))
			{
				continue;
			}

			$modTypes[$type] = $this->app->getContentTypePhrase($type);
		}

		$activeModType = $this->filter('mod', 'str');
		if (!isset($modTypes[$activeModType]))
		{
			$activeModType = '';
		}

		$viewParams = [
			'search' => $search,
			'results' => $results,

			'page' => $page,
			'perPage' => $perPage,

			'getOlderResultsDate' => $getOlderResultsDate,

			'modTypes' => $modTypes,
			'activeModType' => $activeModType,
		];
		return $this->view('XF:Search\Results', 'search_results', $viewParams);
	}

	public function actionAutoComplete(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		$suggestEnabled = $this->options()->searchSuggestions['enabled'];
		if (!$suggestEnabled)
		{
			return $this->notFound();
		}

		$searcher = $this->app->search();
		if (!$searcher->isAutoCompleteSupported())
		{
			return $this->notFound();
		}

		$input = $this->getSearchInput();

		$query = $this->prepareSearchQuery($input, $constraints);

		$searchPlugin = $this->plugin(SearchPlugin::class);
		$searchPlugin->assertValidSearchQuery($query);

		$results = $searcher->autoComplete(
			$query,
			static::MAX_AUTO_COMPLETE_RESULTS
		);
		$resultSet = $searcher->getResultSet($results)->limitToViewableResults();
		$q = $query->getKeywords();
		$autoCompleteResults = $searcher->getAutoCompleteResults($resultSet, [
			'q' => $q,
		]);

		$viewParams = [
			'results' => $autoCompleteResults,
			'q' => $q,
		];
		return $this->view('XF:Search\AutoComplete', '', $viewParams);
	}

	/**
	 * @param array{
	 *     search_type?: string,
	 *     keywords?: string,
	 *     c?: array,
	 *     order?: string,
	 *     grouped?: bool,
	 * } $input
	 *
	 * @return array{
	 *     search_type?: string,
	 *     keywords?: string,
	 *     c?: array,
	 *     order?: string,
	 *     grouped?: bool,
	 * }
	 */
	protected function mergeInputFromSearchMenu($input)
	{
		if (!$this->request->exists('from_search_menu'))
		{
			return $input;
		}

		// TODO: handle context restrictions?
		$menuInput = $this->getSearchInput();

		return array_replace_recursive($input, $menuInput);
	}

	/**
	 * @return array{
	 *     search_type?: string,
	 *     keywords?: string,
	 *     c?: array,
	 *     order?: string,
	 *     grouped?: bool,
	 * }
	 */
	protected function convertShortSearchInputNames()
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		return $searchPlugin->convertShortSearchInputNames();
	}

	/**
	 * @param array{
	 *     t?: string,
	 *     q?: string,
	 *     c?: array,
	 *     o?: string,
	 *     g?: bool,
	 * } $input
	 *
	 * @return array{
	 *     search_type?: string,
	 *     keywords?: string,
	 *     c?: array,
	 *     order?: string,
	 *     grouped?: 1,
	 * }
	 */
	protected function convertShortSearchNames(array $input)
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		return $searchPlugin->convertShortSearchNames($input);
	}

	/**
	 * @return array{
	 *     search_type?: string,
	 *     keywords?: string,
	 *     c?: array|null,
	 *     order?: string,
	 *     grouped?: 1,
	 * }
	 */
	protected function convertSearchToQueryInput(Search $search)
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		return $searchPlugin->convertSearchToQueryInput($search);
	}

	/**
	 * @return array{
	 *     search_type: string,
	 *     keywords: string,
	 *     c: array,
	 *     order: string|null,
	 *     grouped: bool,
	 * }
	 */
	protected function getSearchInput()
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		return $searchPlugin->getSearchInput();
	}

	/**
	 * @return array{
	 *     search_type: string,
	 *     keywords: string,
	 *     c: string,
	 *     order: string,
	 *     grouped: string,
	 * }
	 */
	protected function getSearchInputFilters()
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		return $searchPlugin->getSearchInputFilters();
	}

	/**
	 * @param array{
	 *     search_type: string,
	 *     keywords: string,
	 *     c: array|null,
	 *     order: string
	 *     grouped: int<0, 1>,
	 * } $data
	 *
	 * @return KeywordQuery
	 */
	protected function prepareSearchQuery(array $data, &$urlConstraints = [])
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		return $searchPlugin->prepareSearchQuery($data, $urlConstraints);
	}

	/**
	 * @return AbstractReply
	 */
	protected function runSearch(
		KeywordQuery $query,
		array $constraints,
		$allowCached = true
	)
	{
		$searchRepo = $this->repository(SearchRepository::class);
		$search = $searchRepo->runSearch($query, $constraints, $allowCached);

		if (!$search)
		{
			return $this->message(\XF::phrase('no_results_found'));
		}

		return $this->redirect($this->buildLink('search', $search), '');
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('searching');
	}
}

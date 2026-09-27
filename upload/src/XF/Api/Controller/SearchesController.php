<?php

namespace XF\Api\Controller;

use XF\ControllerPlugin\SearchPlugin;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\SearchRepository;
use XF\Search\Query\KeywordQuery;

/**
 * @api-group Search
 */
class SearchesController extends AbstractController
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
	 * @api-desc Creates a new search
	 *
	 * @api-in string $search_type
	 * @api-in string $keywords
	 * @api-in array $c
	 * @api-in string $order
	 * @api-in bool $grouped
	 *
	 * @api-out true $success
	 * @api-out Search $search
	 */
	public function actionPost(): AbstractReply
	{
		$searchPlugin = $this->plugin(SearchPlugin::class);

		$input = $searchPlugin->getSearchInput();
		$query = $searchPlugin->prepareSearchQuery($input, $constraints);
		$searchPlugin->assertValidSearchQuery($query);

		return $this->runSearch($query, $constraints);
	}

	/**
	 * @api-desc Retrieves search results for a specific member
	 *
	 * @api-in <req> int $user_id
	 * @api-in string $content
	 * @api-in string $type
	 * @api-in int $before
	 * @api-in string $thread_type
	 * @api-in bool $grouped
	 *
	 * @api-out true $success
	 * @api-out Search $search
	 */
	public function actionPostMember(): AbstractReply
	{
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

		$searchPlugin = $this->plugin(SearchPlugin::class);
		$query = $searchPlugin->prepareSearchQuery($input, $constraints);
		$searchPlugin->assertValidSearchQuery($query);

		return $this->runSearch($query, $constraints);
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

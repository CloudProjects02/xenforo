<?php

namespace XF\ControllerPlugin;

use XF\Entity\Search;
use XF\Http\Request;
use XF\Repository\UserRepository;
use XF\Search\Query\KeywordQuery;
use XF\Util\Arr;

use function in_array, is_array;

class SearchPlugin extends AbstractPlugin
{
	/**
	 * @return array{
	 *     search_type?: string,
	 *     keywords?: string,
	 *     c?: array,
	 *     order?: string,
	 *     grouped?: bool,
	 * }
	 */
	public function convertShortSearchInputNames(): array
	{
		return $this->convertShortSearchNames($this->filter([
			't' => 'str',
			'q' => 'str',
			'c' => 'array',
			'o' => 'str',
			'g' => 'bool',
		]));
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
	public function convertShortSearchNames(array $input): array
	{
		$output = [];

		$searchType = $input['t'] ?? '';
		if ($searchType !== '')
		{
			$output['search_type'] = $searchType;
		}

		$keywords = $input['q'] ?? '';
		if ($keywords !== '')
		{
			$output['keywords'] = $input['q'];
		}

		$constraints = $input['c'] ?? [];
		if ($constraints !== [])
		{
			$output['c'] = $constraints;
		}

		$order = $input['o'] ?? '';
		if ($order !== '')
		{
			$output['order'] = $order;
		}

		$grouped = $input['g'] ?? false;
		if ($grouped)
		{
			$output['grouped'] = 1;
		}

		return $output;
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
	public function convertSearchToQueryInput(Search $search): array
	{
		$input = [];

		$searchType = $search->search_type;
		if ($searchType !== '')
		{
			$input['search_type'] = $searchType;
		}

		$keywords = $search->search_query;
		if ($keywords !== '')
		{
			$input['keywords'] = $keywords;
		}

		$c = $search->search_constraints;
		if ($c !== [])
		{
			$input['c'] = $c;
		}

		$order = $search->search_order;
		if ($order !== '')
		{
			$input['order'] = $order;
		}

		$grouped = $search->search_grouping;
		if ($grouped)
		{
			$input['grouped'] = 1;
		}

		return $input;
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
	public function getSearchInput(): array
	{
		$filters = $this->getSearchInputFilters();
		$input = $this->filter($filters);

		$constraintInput = $this->filter('constraints', 'json-array');
		foreach ($filters AS $k => $type)
		{
			if (!isset($constraintInput[$k]))
			{
				continue;
			}

			$cleaned = $this->app->inputFilterer()->filter(
				$constraintInput[$k],
				$type
			);
			$input[$k] = is_array($cleaned)
				? array_merge($input[$k], $cleaned)
				: $cleaned;
		}

		return $input;
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
	public function getSearchInputFilters(): array
	{
		return [
			'search_type' => 'str',
			'keywords' => 'str',
			'c' => 'array',
			'order' => '?str',
			'grouped' => 'bool',
		];
	}

	/**
	 * @param array{
	 *     search_type: string,
	 *     keywords: string,
	 *     c: array|null,
	 *     order: string
	 *     grouped: int<0, 1>,
	 * } $data
	 */
	public function prepareSearchQuery(array $data, &$urlConstraints = []): KeywordQuery
	{
		$searchRequest = new Request(
			$this->app->inputFilterer(),
			$data,
			[],
			[]
		);
		$input = $searchRequest->filter([
			'search_type' => 'str',
			'keywords' => 'str',
			'c' => 'array',
			'c.content' => 'str',
			'c.type' => 'str',
			'c.title_only' => 'uint',
			'c.users' => 'str',
			'c.newer_than' => 'datetime',
			'c.older_than' => 'datetime',
			'c.thread_type' => 'str',
			'grouped' => 'bool',
			'order' => 'str',
		]);

		$urlConstraints = $input['c'];

		$searcher = $this->app()->search();
		$query = $searcher->getQuery();

		if (
			$input['search_type'] !== ''
			&& $searcher->isValidContentType($input['search_type'])
		)
		{
			$typeHandler = $searcher->handler($input['search_type']);
			$query->forTypeHandler(
				$typeHandler,
				$searchRequest,
				$urlConstraints
			);

			$searchableTypes = $typeHandler->getSearchableContentTypes();
			if (
				$input['c.content'] !== ''
				&& in_array($input['c.content'], $searchableTypes, true)
			)
			{
				$query->inType($input['c.content']);
			}
			else
			{
				unset($urlConstraints['content']);

				if (
					$input['c.type'] !== ''
					&& in_array($input['c.type'], $searchableTypes, true)
				)
				{
					$query->inType($input['c.type']);
				}
				else
				{
					unset($urlConstraints['type']);
				}
			}
		}

		$titleOnly = $input['c.title_only'] ? true : false;
		if (!$titleOnly)
		{
			unset($urlConstraints['c.title_only']);
		}

		$input['keywords'] = $this->app->stringFormatter()->censorText(
			$input['keywords'],
			''
		);
		if ($input['keywords'] !== '')
		{
			$query->withKeywords($input['keywords'], $titleOnly);
		}

		if ($input['c.users'] !== '')
		{
			$users = Arr::stringToArray($input['c.users'], '/,\s*/');
			if ($users)
			{
				$userRepo = $this->repository(UserRepository::class);
				$matchedUsers = $userRepo->getUsersByNames($users, $notFound);
				if ($notFound)
				{
					$query->error(
						'users',
						\XF::phrase('following_members_not_found_x', [
							'members' => implode(', ', $notFound),
						])
					);
				}
				else
				{
					$query->byUserIds($matchedUsers->keys());
					$urlConstraints['users'] = implode(', ', $users);
				}
			}
		}
		else
		{
			unset($urlConstraints['users']);
		}

		if ($input['c.newer_than'] !== 0)
		{
			$query->newerThan($input['c.newer_than']);
		}
		else
		{
			unset($urlConstraints['newer_than']);
		}

		if ($input['c.older_than'] !== 0)
		{
			$query->olderThan($input['c.older_than']);
		}
		else
		{
			unset($urlConstraints['older_than']);
		}

		if ($input['c.thread_type'] !== '' && $query->getTypes() === ['thread'])
		{
			$query->withMetadata('thread_type', $input['c.thread_type']);
		}
		else
		{
			unset($urlConstraints['thread_type']);
		}

		if ($input['grouped'])
		{
			$query->withGroupedResults();
		}

		if ($input['order'] !== '')
		{
			$query->orderedBy($input['order']);
		}

		return $query;
	}

	public function assertValidSearchQuery(KeywordQuery $query): void
	{
		$errors = $query->getErrors();
		if ($errors)
		{
			throw $this->exception($this->error($errors));
		}

		$searcher = $this->app()->search();
		if ($searcher->isQueryEmpty($query, $error))
		{
			throw $this->exception($this->error($error));

		}
	}
}

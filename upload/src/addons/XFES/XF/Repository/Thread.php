<?php

namespace XFES\XF\Repository;

use XFES\Entity\ThreadSimilar;
use XFES\Search\Query\FunctionOrder;
use XFES\Search\Query\MoreLikeThisQuery;
use XFES\XF\Search\Search;

use function floatval;

class Thread extends XFCP_Thread
{
	/**
	 * @param \XF\Entity\Thread $thread
	 *
	 * @return bool
	 */
	public function flagIfSimilarThreadsCacheNeedsRebuild(
		\XF\Entity\Thread $thread
	): bool
	{
		/** @var ThreadSimilar $cache */
		$cache = $thread->getRelationOrDefault('XFES_SimilarThreads');
		if (!$cache->exists())
		{
			$cache->pending_rebuild = true;
			$cache->save();
			return true;
		}

		if ($cache->pending_rebuild)
		{
			return true;
		}

		if ($cache->isRebuildRequired())
		{
			$cache->fastUpdate('pending_rebuild', 1);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param \XF\Entity\Thread $thread
	 *
	 * @return ThreadSimilar
	 */
	public function rebuildSimilarThreadsCache(\XF\Entity\Thread $thread): ThreadSimilar
	{
		$threadIds = $this->getSimilarThreadIds(
			$thread,
			ThreadSimilar::MAX_RESULTS,
			false
		);

		/** @var ThreadSimilar $cache */
		$cache = $thread->getRelationOrDefault('XFES_SimilarThreads');
		$cache->pending_rebuild = false;
		$cache->last_update_date = \XF::$time;
		$cache->similar_thread_ids = $threadIds;
		$cache->save(false);

		return $cache;
	}

	public function clearSimilarThreadsCaches()
	{
		$this->db()->emptyTable('xf_es_thread_similar');
	}

	/**
	 * @param \XF\Entity\Thread $thread
	 * @param int|null          $maxResults
	 * @param bool              $applyVisitorPermissions
	 *
	 * @return int[]
	 */
	public function getSimilarThreadIds(
		\XF\Entity\Thread $thread,
		$maxResults = null,
		bool $applyVisitorPermissions = true
	): array
	{
		/** @var Search $searcher */
		$searcher = $this->app()->search();

		$results = $searcher->moreLikeThis(
			$this->getSimilarThreadsMltQuery($thread),
			$maxResults,
			$applyVisitorPermissions
		);

		$threadIds = [];
		foreach ($results AS $result)
		{
			$threadIds[] = $result[1];
		}

		return $threadIds;
	}

	/**
	 * @param \XF\Entity\Thread $thread
	 *
	 * @return MoreLikeThisQuery
	 */
	public function getSimilarThreadsMltQuery(
		\XF\Entity\Thread $thread
	): MoreLikeThisQuery
	{
		/** @var Search $searcher */
		$searcher = $this->app()->search();

		$query = $searcher->getMoreLikeThisQuery();
		$query
			->like($thread)
			->inType('thread')
			->allowHidden(false);

		$boost = floatval($this->app()->options()->xfesSimilarThreads['forumBoost']);
		if ($boost > 1)
		{
			$query->orderedBy(new FunctionOrder([
				'filter' => ['term' => ['node' => $thread->node_id]],
				'weight' => $boost,
			]));
		}

		return $query;
	}
}

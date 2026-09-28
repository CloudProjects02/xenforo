<?php

namespace XFES\XF\Search;

use XFES\Search\Query\MoreLikeThisQuery;

class Search extends XFCP_Search
{
	/**
	 * @return MoreLikeThisQuery
	 */
	public function getMoreLikeThisQuery()
	{
		$extendClass = \XF::extendClass('XFES\Search\Query\MoreLikeThisQuery');
		return new $extendClass($this);
	}

	/**
	 * @param MoreLikeThisQuery $query
	 * @param int|null                             $maxResults
	 * @param bool                                 $applyVisitorPermissions
	 *
	 * @return array
	 */
	public function moreLikeThis(
		MoreLikeThisQuery $query,
		$maxResults = null,
		$applyVisitorPermissions = true
	)
	{
		$this->assertEnhancedSearchEnabled();

		return $this->executeSearch(
			$query,
			$maxResults,
			function ($query, $maxResults)
			{
				return $this->source->moreLikeThis($query, $maxResults);
			},
			$applyVisitorPermissions
		);
	}

	/**
	 * @throws \LogicException
	 */
	public function assertEnhancedSearchEnabled()
	{
		if ($this->isEnhancedSearchEnabled())
		{
			return;
		}

		throw new \LogicException('Enhanced searching is not enabled.');
	}

	/**
	 * @return bool
	 */
	public function isEnhancedSearchEnabled()
	{
		return \XF::app()->options()->xfesEnabled;
	}
}

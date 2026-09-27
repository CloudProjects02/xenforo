<?php

namespace DBTech\Credits\Pub\Controller;

use DBTech\Credits\Finder\ChargePurchaseFinder;
use DBTech\Credits\Pub\View;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;
use XF\Pub\Controller\AbstractController;

class ChargeController extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ReplyException
	 * @throws \Exception
	 */
	public function actionUnlocked(ParameterBag $params): AbstractReply
	{
		$this->assertRegistrationRequired();

		$chargeFinder = \XF::app()->finder(ChargePurchaseFinder::class)
			->where('user_id', \XF::visitor()->user_id)
			->order('content_id', 'DESC')
		;

		$total = $chargeFinder->total();
		if (!$total)
		{
			return $this->error(\XF::phrase('dbtech_credits_could_not_find_unlocked_content'));
		}

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->searchResultsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-credits/charge/unlocked');

		$maxResults = max(\XF::options()->maximumSearchResults, 20);

		$results = $chargeFinder->fetch();
		$resultArray = [];
		foreach ($results AS $key => $result)
		{
			$resultArray[$key] = $result->toArray();
		}

		$searcher = \XF::app()->search();
		$resultSet = $searcher->getResultSet($resultArray)->limitResults($maxResults);

		$resultSet->sliceResultsToPage($page, $perPage);

		if (!$resultSet->countResults())
		{
			return $this->message(\XF::phrase('no_results_found'));
		}

		$maxPage = ceil($total / $perPage);

		if ($total > $perPage
			&& $page == $maxPage)
		{
			$lastResult = $resultSet->getLastResultData($lastResultType);
			$getOlderResultsDate = $searcher->handler($lastResultType)->getResultDate($lastResult);
		}
		else
		{
			$getOlderResultsDate = null;
		}

		$resultOptions = [
			'search' => null,
		];
		$resultsWrapped = $searcher->wrapResultsForRender($resultSet, $resultOptions);

		$viewParams = [
			'results' => $resultsWrapped,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,

			'getOlderResultsDate' => $getOlderResultsDate,
		];
		return $this->view(
			View\Charge\UnlockedView::class,
			'dbtech_credits_charge_search_results',
			$viewParams
		);
	}
}
<?php

namespace DBTech\Credits\Pub\Controller;

use DBTech\Credits\Entity\ContentAccessPurchase;
use DBTech\Credits\Finder\ContentAccessPurchaseFinder;
use DBTech\Credits\Pub\View;
use DBTech\Credits\Repository\ContentAccessRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\XF\Entity\User;
use XF\Db\DuplicateKeyException;
use XF\Entity\LinkableInterface;
use XF\InputFilterer;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class ContentAccessController extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		$input = $this->filter([
			'content_type' => InputFilterer::STRING,
			'content_id' => InputFilterer::UNSIGNED,
		]);

		$contentAccessHandler = \XF::app()->repository(ContentAccessRepository::class)
			->getHandler($input['content_type'], false)
		;
		if (!$contentAccessHandler)
		{
			return $this->notFound();
		}

		/** @var Entity $content */
		$content = $contentAccessHandler->getContent($input['content_id']);
		if (!$content)
		{
			return $this->notFound();
		}

		if (!$content->isValidRelation('ContentAccessPurchases'))
		{
			throw new \LogicException(
				"The 'ContentAccessPurchases' relation did not exist on the $input[content_type] entity."
			);
		}

		if (!$content->isValidRelation('ContentAccessCurrency'))
		{
			throw new \LogicException(
				"The 'ContentAccessCurrency' relation did not exist on the $input[content_type] entity."
			);
		}

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($content->ContentAccessPurchases->offsetExists($visitor->user_id))
		{
			return $this->error(\XF::phrase('dbtech_credits_already_owned'));
		}

		if ($this->isPost())
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			$contentAccessEvent = $eventTriggerRepo->getHandler('content_access');
			$extraParams = [
				'node_id' => $content->isValidColumn('node_id') ? $content->node_id : 0,
				'multiplier' => (-1 * $content->dbtech_credits_access_cost),
				'currency_id' => $content->dbtech_credits_access_currency_id,
				'content_type' => $contentAccessHandler->getContentType(),
				'content_id' => $input['content_id'],
				'alwaysCheck' => true,
			];

			$pendingTransactions = $contentAccessEvent->testUndo($extraParams, $visitor);

			if (!count($pendingTransactions))
			{
				return $this->error(\XF::phrase('dbtech_credits_content_purchase_events_invalid'));
			}

			// Charge the current user
			$contentAccessEvent->undo($input['content_id'], $extraParams, $visitor);

			if (!empty($content->User))
			{
				// Add this
				$extraParams['multiplier'] = $content->dbtech_credits_access_cost;
				$extraParams['source_user_id'] = $visitor->user_id;

				// Apply the event to the post owner, in case ownership settings are configured
				$contentAccessEvent->apply($input['content_id'], $extraParams, $content->User);
			}

			try
			{
				$contentAccessPurchase = \XF::app()->em()->create(ContentAccessPurchase::class);
				$contentAccessPurchase->content_type = $contentAccessHandler->getContentType();
				$contentAccessPurchase->content_id = $input['content_id'];
				$contentAccessPurchase->user_id = $visitor->user_id;
				$contentAccessPurchase->save();
			}
			/** @noinspection PhpRedundantCatchClauseInspection */
			catch (DuplicateKeyException $e)
			{
			}

			$redirect = $this->getDynamicRedirect();
			if ($content instanceof LinkableInterface)
			{
				// Redirect back to the content if we can
				$redirect = $content->getContentUrl();
			}

			// And we're done
			return $this->redirect(
				$redirect,
				\XF::phrase('dbtech_credits_unlock_successful')
			);
		}

		if ($content instanceof LinkableInterface)
		{
			$title = $content->getContentTitle();
			$contentUrl = $content->getContentUrl();
		}
		else
		{
			$title = $content->isValidColumn('title') ? $content->title : \XF::phrase('n_a');
			$contentUrl = '';
		}

		$viewParams = [
			'currency' => $content->ContentAccessCurrency,
			'content' => $content,
			'contentType' => $contentAccessHandler->getContentType(),
			'contentId' => $input['content_id'],
			'title' => $title,
			'contentUrl' => $contentUrl,
		];
		return $this->view(
			View\ContentAccess\UnlockView::class,
			'dbtech_credits_content_access_unlock',
			$viewParams
		);
	}

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

		$contentAccessFinder = \XF::app()->finder(ContentAccessPurchaseFinder::class)
			->where('user_id', \XF::visitor()->user_id)
			->order('content_id', 'DESC')
		;

		$total = $contentAccessFinder->total();
		if (!$total)
		{
			return $this->error(\XF::phrase('dbtech_credits_could_not_find_unlocked_content'));
		}

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->searchResultsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-credits/content-access/unlocked');

		$maxResults = max(\XF::options()->maximumSearchResults, 20);

		$results = $contentAccessFinder->fetch();
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
			View\ContentAccess\UnlockedView::class,
			'dbtech_credits_content_access_search_results',
			$viewParams
		);
	}
}
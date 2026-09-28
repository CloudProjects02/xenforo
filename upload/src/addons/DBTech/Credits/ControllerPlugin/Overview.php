<?php

namespace DBTech\Credits\ControllerPlugin;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Entity\Event;
use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Finder\TransactionFinder;
use DBTech\Credits\Pub\View\FiltersView;
use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\Repository\TransactionRepository;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Entity\User;
use XF\InputFilterer;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\AbstractReply;

class Overview extends AbstractPlugin
{
	/**
	 * @return array
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function getCoreListData(): array
	{
		$transactionRepo = \XF::app()->repository(TransactionRepository::class);

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		$transactionFinder = $transactionRepo->findTransactionsForOverviewList([
			'allowOwnPending' => $allowOwnPending,
		]);

		$filters = $this->getTransactionFilterInput();
		$this->applyTransactionFilters($transactionFinder, $filters);

		$totalTransactions = $transactionFinder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtech_credits_transactions;

		$transactionFinder->limitByPage($page, $perPage);
		$transactions = $transactionFinder->fetch()->filterViewable();

		if (!empty($filters['source_id']))
		{
			$sourceFilter = \XF::app()->em()->find(User::class, $filters['source_id']);
		}
		else
		{
			$sourceFilter = null;
		}

		if (!empty($filters['target_id']))
		{
			$targetFilter = \XF::app()->em()->find(User::class, $filters['target_id']);
		}
		else
		{
			$targetFilter = null;
		}

		if (!empty($filters['currency_id']))
		{
			$currencyFilter = \XF::app()->em()->find(Currency::class, $filters['currency_id']);
		}
		else
		{
			$currencyFilter = null;
		}

		if (!empty($filters['event_id']))
		{
			$eventFilter = \XF::app()->em()->find(Event::class, $filters['event_id']);
		}
		else
		{
			$eventFilter = null;
		}

		if (!empty($filters['event_trigger_id']))
		{
			$eventTriggerFilter = \XF::app()->repository(EventTriggerRepository::class)
				->getHandler($filters['event_trigger_id'])
			;
		}
		else
		{
			$eventTriggerFilter = null;
		}

		$this->addContentToResults($transactions);

		return [
			'transactions' => $transactions,
			'filters' => $filters,
			'sourceFilter' => $sourceFilter,
			'targetFilter' => $targetFilter,
			'currencyFilter' => $currencyFilter,
			'eventFilter' => $eventFilter,
			'eventTriggerFilter' => $eventTriggerFilter,

			'total' => $totalTransactions,
			'page' => $page,
			'perPage' => $perPage,
		];
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Transaction> $transactions
	 */
	public function addContentToResults(AbstractCollection $transactions): void
	{
		$byType = [];
		foreach ($transactions AS $result)
		{
			if (empty($result->content_type) || empty($result->content_id))
			{
				continue;
			}

			$byType[$result->content_type][$result->transaction_id] = $result->content_id;
		}

		foreach ($byType AS $type => $ids)
		{
			try
			{
				$entities = \XF::app()->findByContentType($type, $ids);

				foreach ($ids AS $transactionId => $contentId)
				{
					if (!$transactions->offsetExists($transactionId)
						|| !$entities->offsetExists($contentId)
					)
					{
						continue;
					}

					/** @var Transaction $transaction */
					$transaction = $transactions->offsetGet($transactionId);

					$transaction->setContent($entities->offsetGet($contentId));
				}
			}
			catch (\LogicException $e)
			{
			}
		}
	}

	/**
	 * @param TransactionFinder $transactionFinder
	 * @param array $filters
	 */
	public function applyTransactionFilters(TransactionFinder $transactionFinder, array $filters): void
	{
		if (!empty($filters['source_id']))
		{
			$transactionFinder->where('source_user_id', (int) $filters['source_id']);
		}

		if (!empty($filters['target_id']))
		{
			$transactionFinder->where('user_id', (int) $filters['target_id']);
		}

		if (!empty($filters['currency_id']))
		{
			$transactionFinder->where('currency_id', (int) $filters['currency_id']);
		}

		if (!empty($filters['event_id']))
		{
			$transactionFinder->where('event_id', (int) $filters['event_id']);
		}

		if (!empty($filters['event_trigger_id']))
		{
			$transactionFinder->where('event_trigger_id', $filters['event_trigger_id']);
		}

		$sorts = $this->getAvailableTransactionSorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$transactionFinder->order($sorts[$filters['order']], $filters['direction']);
		}
	}

	/**
	 * @return array
	 */
	public function getTransactionFilterInput(): array
	{
		$filters = [];

		$input = $this->filter([
			'prefix_id' => InputFilterer::UNSIGNED,
			'type' => InputFilterer::STRING,
			'source' => InputFilterer::STRING,
			'source_id' => InputFilterer::UNSIGNED,
			'target' => InputFilterer::STRING,
			'target_id' => InputFilterer::UNSIGNED,
			'currency_id' => InputFilterer::UNSIGNED,
			'event_id' => InputFilterer::UNSIGNED,
			'event_trigger_id' => InputFilterer::STRING,
			'order' => InputFilterer::STRING,
			'direction' => InputFilterer::STRING,
		]);

		if ($input['prefix_id'])
		{
			$filters['prefix_id'] = $input['prefix_id'];
		}

		if (($input['type'] == 'free' || $input['type'] == 'paid'))
		{
			$filters['type'] = $input['type'];
		}

		if ($input['source_id'])
		{
			$filters['source_id'] = $input['source_id'];
		}
		else if ($input['source'])
		{
			$user = \XF::app()->em()->findOne(User::class, ['username' => $input['source']]);
			if ($user)
			{
				$filters['source_id'] = $user->user_id;
			}
		}

		if ($input['target_id'])
		{
			$filters['target_id'] = $input['target_id'];
		}
		else if ($input['target'])
		{
			$user = \XF::app()->em()->findOne(User::class, ['username' => $input['target']]);
			if ($user)
			{
				$filters['target_id'] = $user->user_id;
			}
		}

		if ($input['currency_id'])
		{
			$filters['currency_id'] = $input['currency_id'];
		}

		if ($input['event_id'])
		{
			$filters['event_id'] = $input['event_id'];
		}

		if ($input['event_trigger_id'])
		{
			$filters['event_trigger_id'] = $input['event_trigger_id'];
		}

		$sorts = $this->getAvailableTransactionSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			//			$defaultOrder = \XF::app()->options()->dbtechCreditsListDefaultOrder ?: 'dateline';
			$defaultOrder = 'dateline';
			/** @noinspection PhpConditionAlreadyCheckedInspection */
			$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

			if ($input['order'] != $defaultOrder || $input['direction'] != $defaultDir)
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}

		return $filters;
	}

	/**
	 * @return array
	 */
	public function getAvailableTransactionSorts(): array
	{
		// maps [name of sort] => field in/relative to Transaction entity
		return [
			'dateline' => 'dateline',
			'amount' => 'amount',
		];
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionFilters(): AbstractReply
	{
		$filters = $this->getTransactionFilterInput();

		if ($this->filter('apply', InputFilterer::BOOLEAN))
		{
			return $this->redirect($this->buildLink(
				'dbtech-credits',
				null,
				$filters
			));
		}

		if (!empty($filters['source_id']))
		{
			$sourceFilter = \XF::app()->em()->find(User::class, $filters['source_id']);
		}
		else
		{
			$sourceFilter = null;
		}

		if (!empty($filters['target_id']))
		{
			$targetFilter = \XF::app()->em()->find(User::class, $filters['target_id']);
		}
		else
		{
			$targetFilter = null;
		}

		//		$defaultOrder = \XF::app()->options()->dbtechCreditsListDefaultOrder ?: 'dateline';
		$defaultOrder = 'dateline';
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		if (empty($filters['order']))
		{
			$filters['order'] = $defaultOrder;
		}
		if (empty($filters['direction']))
		{
			$filters['direction'] = $defaultDir;
		}

		$viewParams = [
			'filters' => $filters,
			'sourceFilter' => $sourceFilter,
			'targetFilter' => $targetFilter,
			'currencyFilter' => \XF::app()->repository(CurrencyRepository::class)
				->getCurrencyTitlePairs(true)
			,
			'eventFilter' => \XF::app()->repository(EventTriggerRepository::class)
				->getEventTitlePairs(true, true)
			,
			'eventTriggerFilter' => \XF::app()->repository(EventTriggerRepository::class)
				->getEventTriggerTitlePairs(true, true)
			,
		];
		return $this->view(
			FiltersView::class,
			'dbtech_credits_filters',
			$viewParams
		);
	}
}
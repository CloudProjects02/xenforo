<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\TransactionLog;
use DBTech\Shop\Finder\TransactionLogFinder;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\TransactionRepository;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\Pub\Controller\AbstractController;

class TransactionsController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertRegistrationRequired();

		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		if ($params->transaction_log_id)
		{
			$entry = $this->assertViewableTransactionLog($params->transaction_log_id, [
				'User',
				'Recipient',
				'Ip',
			]);

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Transaction\ViewView::class,
				'dbtech_shop_transaction_view',
				$viewParams
			);
		}

		$transactionRepo = \XF::app()->repository(TransactionRepository::class);
		$transactionFinder = $transactionRepo->findTransactionsForOverviewList();

		$filters = $this->getTransactionFilterInput();
		$this->applyTransactionFilters($transactionFinder, $filters);

		$totalTransactions = $transactionFinder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopTransactions;

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

		if (!empty($filters['recipient_id']))
		{
			$recipientFilter = \XF::app()->em()->find(User::class, $filters['recipient_id']);
		}
		else
		{
			$recipientFilter = null;
		}

		if (!empty($filters['action_id']))
		{
			$actionFilter = \XF::app()->repository(TransactionRepository::class)
				->getActionTitle($filters['action_id'])
			;
		}
		else
		{
			$actionFilter = null;
		}

		$viewParams = [
			'entries' => $transactions,
			'filters' => $filters,
			'sourceFilter' => $sourceFilter,
			'recipientFilter' => $recipientFilter,
			'actionFilter' => $actionFilter,

			'total' => $totalTransactions,
			'page' => $page,
			'perPage' => $perPage,
		];

		$this->assertValidPage($viewParams['page'], $viewParams['perPage'], $viewParams['total'], 'dbtech-shop/transactions');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/transactions', null, ['page' => $viewParams['page']]));

		return $this->view(
			View\Transaction\ListingView::class,
			'dbtech_shop_transaction_list',
			$viewParams
		);
	}

	/**
	 * @param TransactionLogFinder $transactionFinder
	 * @param array $filters
	 */
	public function applyTransactionFilters(TransactionLogFinder $transactionFinder, array $filters): void
	{
		if (!empty($filters['source_id']))
		{
			$transactionFinder->where('user_id', (int) $filters['source_id']);
		}

		if (!empty($filters['recipient_id']))
		{
			$transactionFinder->where('recipient_user_id', (int) $filters['recipient_id']);
		}

		if (!empty($filters['action_id']))
		{
			$transactionFinder->where('action', $filters['action_id']);
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
			'source' => 'str',
			'source_id' => 'uint',
			'recipient' => 'str',
			'recipient_id' => 'uint',
			'action_id' => 'str',
			'order' => 'str',
			'direction' => 'str',
		]);

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

		if ($input['recipient_id'])
		{
			$filters['recipient_id'] = $input['recipient_id'];
		}
		else if ($input['recipient'])
		{
			$user = \XF::app()->em()->findOne(User::class, ['username' => $input['recipient']]);
			if ($user)
			{
				$filters['recipient_id'] = $user->user_id;
			}
		}

		if ($input['action_id'])
		{
			$filters['action_id'] = $input['action_id'];
		}

		$sorts = $this->getAvailableTransactionSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			//			$defaultOrder = \XF::app()->options()->dbtechShopListDefaultOrder ?: 'dateline';
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
			'user' => 'User.username',
			'recipient' => 'Recipient.username',
		];
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionFilters(): AbstractReply
	{
		$filters = $this->getTransactionFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink(
				'dbtech-shop/transactions',
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

		if (!empty($filters['recipient_id']))
		{
			$recipientFilter = \XF::app()->em()->find(User::class, $filters['recipient_id']);
		}
		else
		{
			$recipientFilter = null;
		}

		//		$defaultOrder = \XF::app()->options()->dbtechShopListDefaultOrder ?: 'dateline';
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

		$titlePairs = \XF::app()->repository(TransactionRepository::class)
			->getActionTitlePairs()
		;

		$actionFilter = [];
		foreach ($titlePairs AS $key => $phrase)
		{
			$actionFilter[$key] = $phrase->render();
		}

		asort($actionFilter);

		$viewParams = [
			'filters' => $filters,
			'sourceFilter' => $sourceFilter,
			'recipientFilter' => $recipientFilter,
			'actionFilter' => $actionFilter,
		];
		return $this->view(
			View\FiltersView::class,
			'dbtech_shop_transaction_filters',
			$viewParams
		);
	}

	/**
	 * @param int|null $entryId
	 * @param array $extraWith
	 *
	 * @return TransactionLog
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTransactionLog(?int $entryId, array $extraWith = []): TransactionLog
	{
		$extraWith[] = 'User';
		$extraWith[] = 'Recipient';

		$entry = \XF::app()->em()->find(TransactionLog::class, $entryId, $extraWith);
		if (!$entry)
		{
			throw $this->exception($this->notFound(\XF::phrase('dbtech_shop_requested_transaction_not_found')));
		}

		if (!$entry->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $entry;
	}

	/**
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_bank');
	}
}
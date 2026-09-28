<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Transactions
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Transactions extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
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
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if ($params->transaction_log_id)
		{
			$entry = $this->assertViewableTransactionLog($params->transaction_log_id, [
				'User',
				'Recipient',
				'Ip'
			]);

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('DBTech\Shop:Transaction\View', 'dbtech_shop_transaction_view', $viewParams);
		}

		$transactionRepo = $this->getTransactionRepo();

		$transactionFinder = $transactionRepo->findTransactionsForOverviewList();

		$filters = $this->getTransactionFilterInput();
		$this->applyTransactionFilters($transactionFinder, $filters);

		$totalTransactions = $transactionFinder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopTransactions;

		$transactionFinder->limitByPage($page, $perPage);
		$transactions = $transactionFinder->fetch()->filterViewable();

		if (!empty($filters['source_id']))
		{
			$sourceFilter = $this->em()->find('XF:User', $filters['source_id']);
		}
		else
		{
			$sourceFilter = null;
		}

		if (!empty($filters['recipient_id']))
		{
			$recipientFilter = $this->em()->find('XF:User', $filters['recipient_id']);
		}
		else
		{
			$recipientFilter = null;
		}

		if (!empty($filters['action_id']))
		{
			$actionFilter = $this->getTransactionRepo()->getActionTitle($filters['action_id']);
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
			'perPage' => $perPage
		];

		$this->assertValidPage($viewParams['page'], $viewParams['perPage'], $viewParams['total'], 'dbtech-shop/transactions');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/transactions', null, ['page' => $viewParams['page']]));

		return $this->view('DBTech\Shop:Transaction\Listing', 'dbtech_shop_transaction_list', $viewParams);
	}

	/**
	 * @param \DBTech\Shop\Finder\TransactionLog $transactionFinder
	 * @param array $filters
	 */
	public function applyTransactionFilters(\DBTech\Shop\Finder\TransactionLog $transactionFinder, array $filters)
	{
		if (!empty($filters['source_id']))
		{
			$transactionFinder->where('user_id', (int)$filters['source_id']);
		}

		if (!empty($filters['recipient_id']))
		{
			$transactionFinder->where('recipient_user_id', (int)$filters['recipient_id']);
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
			'direction' => 'str'
		]);

		if ($input['source_id'])
		{
			$filters['source_id'] = $input['source_id'];
		}
		elseif ($input['source'])
		{
			$user = $this->em()->findOne('XF:User', ['username' => $input['source']]);
			if ($user)
			{
				$filters['source_id'] = $user->user_id;
			}
		}

		if ($input['recipient_id'])
		{
			$filters['recipient_id'] = $input['recipient_id'];
		}
		elseif ($input['recipient'])
		{
			$user = $this->em()->findOne('XF:User', ['username' => $input['recipient']]);
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

//			$defaultOrder = $this->options()->dbtechShopListDefaultOrder ?: 'dateline';
			$defaultOrder = 'dateline';
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
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \Exception
	 */
	public function actionFilters()
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
			$sourceFilter = $this->em()->find('XF:User', $filters['source_id']);
		}
		else
		{
			$sourceFilter = null;
		}

		if (!empty($filters['recipient_id']))
		{
			$recipientFilter = $this->em()->find('XF:User', $filters['recipient_id']);
		}
		else
		{
			$recipientFilter = null;
		}

//		$defaultOrder = $this->options()->dbtechShopListDefaultOrder ?: 'dateline';
		$defaultOrder = 'dateline';
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		if (empty($filters['order']))
		{
			$filters['order'] = $defaultOrder;
		}
		if (empty($filters['direction']))
		{
			$filters['direction'] = $defaultDir;
		}

		$titlePairs = $this->getTransactionRepo()->getActionTitlePairs();

		$actionFilter = [];
		foreach ($titlePairs as $key => $phrase)
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
		return $this->view('DBTech\Shop:Filters', 'dbtech_shop_transaction_filters', $viewParams);
	}

	/**
	 * @param int|null $entryId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Shop\Entity\TransactionLog
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTransactionLog(?int $entryId, array $extraWith = []): \DBTech\Shop\Entity\TransactionLog
	{
		$extraWith[] = 'User';
		$extraWith[] = 'Recipient';

		/** @var \DBTech\Shop\Entity\TransactionLog $entry */
		$entry = $this->em()->find('DBTech\Shop:TransactionLog', $entryId, $extraWith);
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
	 * @return \DBTech\Shop\Repository\Transaction|\XF\Mvc\Entity\Repository
	 */
	protected function getTransactionRepo()
	{
		return $this->repository('DBTech\Shop:Transaction');
	}

	/**
	 * @param array $activities
	 *
	 * @return bool|\XF\Phrase
	 */
	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('dbtech_shop_viewing_bank');
	}
}
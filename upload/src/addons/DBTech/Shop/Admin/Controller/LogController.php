<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Admin\View;
use DBTech\Shop\Entity\TransactionLog;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class LogController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\LogView::class,
			'dbtech_shop_logs'
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionTransaction(ParameterBag $params): AbstractReply
	{
		if ($params->transaction_log_id)
		{
			$entry = $this->assertTransactionLogExists($params->transaction_log_id, [
				'User',
				'Recipient',
				'Ip',
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				View\Log\Transaction\ViewView::class,
				'dbtech_shop_log_transaction_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');

		$page = $this->filterPage();
		$perPage = 20;

		/** @var \DBTech\Shop\Searcher\TransactionLog $searcher */
		$searcher = $this->searcher(\DBTech\Shop\Searcher\TransactionLog::class, $criteria);

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
		$finder->with(['User', 'Ip']);
		$finder->limitByPage($page, $perPage);

		$total = $finder->total();
		$entries = $finder->fetch();

		$viewParams = [
			'entries' => $entries,

			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,

			'criteria' => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order' => $order,
			'direction' => $direction,

		];
		return $this->view(
			View\Log\Transaction\ListingView::class,
			'dbtech_shop_log_transaction_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionTransactionSearch(): AbstractReply
	{
		$viewParams = $this->getTransactionLogSearcherParams();

		return $this->view(
			View\Log\Transaction\SearchView::class,
			'dbtech_shop_log_transaction_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 * @return array
	 */
	protected function getTransactionLogSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Shop\Searcher\TransactionLog $searcher */
		$searcher = $this->searcher(\DBTech\Shop\Searcher\TransactionLog::class);

		$viewParams = [
			'criteria' => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions(),
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return TransactionLog
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertTransactionLogExists(?int $id, array $with = [], ?string $phraseKey = null): TransactionLog
	{
		return $this->assertRecordExists(TransactionLog::class, $id, $with, $phraseKey);
	}
}
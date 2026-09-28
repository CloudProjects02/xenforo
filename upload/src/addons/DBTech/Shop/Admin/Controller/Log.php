<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class Log
 * @package DBTech\Shop\Admin\Controller
 */
class Log extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		return $this->view('DBTech\Shop:Log', 'dbtech_shop_logs');
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionTransaction(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if ($params->transaction_log_id)
		{
			$entry = $this->assertTransactionLogExists($params->transaction_log_id, [
				'User',
				'Recipient',
				'Ip'
			], 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('DBTech\Shop:Log\Transaction\View', 'dbtech_shop_log_transaction_view', $viewParams);
		}
		
		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');
		
		$page = $this->filterPage();
		$perPage = 20;
		
		/** @var \DBTech\Shop\Searcher\TransactionLog $searcher */
		$searcher = $this->searcher('DBTech\Shop:TransactionLog', $criteria);
		
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
			'direction' => $direction

		];
		return $this->view('DBTech\Shop:Log\Transaction\Listing', 'dbtech_shop_log_transaction_list', $viewParams);
	}

	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionTransactionSearch(): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = $this->getTransactionLogSearcherParams();

		return $this->view('DBTech\Shop:Log\Transaction\Search', 'dbtech_shop_log_transaction_search', $viewParams);
	}

	/**
	 * @param array $extraParams
	 * @return array
	 */
	protected function getTransactionLogSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Shop\Searcher\TransactionLog $searcher */
		$searcher = $this->searcher('DBTech\Shop:TransactionLog');
		
		$viewParams = [
			'criteria' => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions()
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\TransactionLog|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertTransactionLogExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:TransactionLog', $id, $with, $phraseKey);
	}
}
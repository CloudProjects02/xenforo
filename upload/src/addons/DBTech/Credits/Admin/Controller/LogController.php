<?php

namespace DBTech\Credits\Admin\Controller;

use DBTech\Credits\Admin\View;
use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\Searcher\TransactionLog;
use XF\Admin\Controller\AbstractController;
use XF\InputFilterer;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;

class LogController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws ReplyException
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechCredits');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->view(
			View\LogView::class,
			'dbtech_credits_logs'
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ReplyException
	 * @throws \Exception
	 */
	public function actionTransaction(ParameterBag $params): AbstractReply
	{
		if ($params->transaction_id)
		{
			$entry = $this->assertTransactionLogExists($params->transaction_id, [
				'Event',
				'Currency',
				'TargetUser',
				'SourceUser',
			], 'requested_log_entry_not_found');

			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTrigger = $eventTriggerRepo->getHandler($entry->event_trigger_id);

			$viewParams = [
				'entry' => $entry,
				'eventTrigger' => $eventTrigger,
			];
			return $this->view(
				View\Log\Transaction\ViewView::class,
				'dbtech_credits_log_transaction_view',
				$viewParams
			);
		}

		$criteria = $this->filter('criteria', InputFilterer::ARRAY);
		$order = $this->filter('order', InputFilterer::STRING);
		$direction = $this->filter('direction', InputFilterer::STRING);

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtech_credits_transactions;

		/** @var TransactionLog $searcher */
		$searcher = $this->searcher(TransactionLog::class, $criteria);

		if (empty($criteria))
		{
			$searcher->setCriteria($searcher->getFormDefaults());
		}

		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}

		$searcher->setOrder($order, $direction);

		$finder = $searcher->getFinder();
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
			'dbtech_credits_log_transaction_list',
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
			'dbtech_credits_log_transaction_search',
			$viewParams
		);
	}

	/**
	 * @param array $extraParams
	 * @return array
	 */
	protected function getTransactionLogSearcherParams(array $extraParams = []): array
	{
		/** @var TransactionLog $searcher */
		$searcher = $this->searcher(TransactionLog::class);

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
	 * @return Transaction
	 * @throws ReplyException
	 */
	protected function assertTransactionLogExists(?int $id, array $with = [], ?string $phraseKey = null): Transaction
	{
		return $this->assertRecordExists(Transaction::class, $id, $with, $phraseKey);
	}
}
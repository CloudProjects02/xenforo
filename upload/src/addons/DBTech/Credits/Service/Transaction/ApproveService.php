<?php

namespace DBTech\Credits\Service\Transaction;

use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\Repository\TransactionRepository;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;

class ApproveService extends AbstractService
{
	protected Transaction $transaction;
	protected bool $notify = true;
	protected int $notifyRunTime = 3;
	protected string $reason = '';

	/**
	 * @param App $app
	 * @param Transaction $transaction
	 */
	public function __construct(App $app, Transaction $transaction)
	{
		parent::__construct($app);
		$this->transaction = $transaction;
	}

	/**
	 * @return Transaction
	 */
	public function getUpdate(): Transaction
	{
		return $this->transaction;
	}

	/**
	 * @param bool $notify
	 *
	 * @return $this
	 */
	public function setNotify(bool $notify): ApproveService
	{
		$this->notify = $notify;

		return $this;
	}

	/**
	 * @param int $time
	 *
	 * @return $this
	 */
	public function setNotifyRunTime(int $time): ApproveService
	{
		$this->notifyRunTime = $time;

		return $this;
	}

	/**
	 * @param string $reason
	 *
	 * @return $this
	 */
	public function setReason(string $reason): ApproveService
	{
		$this->reason = $reason;

		return $this;
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function approve(): bool
	{
		if ($this->transaction->transaction_state == 'moderated')
		{
			$this->transaction->transaction_state = 'visible';
			$this->transaction->save();

			$this->onApprove();
			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function reject(): bool
	{
		if ($this->transaction->transaction_state == 'moderated')
		{
			$this->transaction->delete();

			$this->onReject();
			return true;
		}

		return false;
	}

	/**
	 *
	 */
	protected function onApprove(): void
	{
		if ($this->notify)
		{
			$transactionRepo = \XF::app()->repository(TransactionRepository::class);
			$transactionRepo->sendModeratorActionAlert($this->transaction, 'approve', $this->reason);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function onReject(): void
	{
		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
		$handler = $eventTriggerRepo->getHandler($this->transaction->event_trigger_id);

		$handler->onReject($this->transaction);

		if ($this->notify)
		{
			$transactionRepo = \XF::app()->repository(TransactionRepository::class);
			$transactionRepo->sendModeratorActionAlert($this->transaction, 'reject', $this->reason);
		}
	}
}
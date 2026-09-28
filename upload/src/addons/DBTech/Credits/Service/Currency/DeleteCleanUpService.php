<?php

namespace DBTech\Credits\Service\Currency;

use DBTech\Credits\Finder\TransactionFinder;
use XF\App;
use XF\ContinuationResult;
use XF\MultiPartRunnerTrait;
use XF\PrintableException;
use XF\Service\AbstractService;

class DeleteCleanUpService extends AbstractService
{
	use MultiPartRunnerTrait;

	protected int $currencyId;
	protected string $title;
	protected array $steps = [
		'stepDeleteTransactions',
	];


	/**
	 * @param App $app
	 * @param int $currencyId
	 * @param string $title
	 */
	public function __construct(App $app, int $currencyId, string $title)
	{
		parent::__construct($app);

		$this->currencyId = $currencyId;
		$this->title = $title;
	}

	/**
	 * @return array
	 */
	protected function getSteps(): array
	{
		return $this->steps;
	}

	/**
	 * @param float|int $maxRunTime
	 *
	 * @return ContinuationResult
	 */
	public function cleanUp(float|int $maxRunTime = 0): ContinuationResult
	{
		$this->db()->beginTransaction();
		$result = $this->runLoop($maxRunTime);
		$this->db()->commit();

		return $result;
	}

	/**
	 * @param int|null $lastOffset
	 * @param float|int|null $maxRunTime
	 *
	 * @return int|null
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws PrintableException
	 */
	protected function stepDeleteTransactions(?int $lastOffset, float|int|null $maxRunTime): ?int
	{
		$start = microtime(true);

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Transaction> $transactions */
		$finder = \XF::app()->finder(TransactionFinder::class)
			->where('currency_id', $this->currencyId)
			->order('transaction_id');

		if ($lastOffset !== null)
		{
			$finder->where('transaction_id', '>', $lastOffset);
		}

		$maxFetch = 1000;
		$transactions = $finder->fetch($maxFetch);
		$fetchedTransactions = count($transactions);

		if (!$fetchedTransactions)
		{
			return null; // done or nothing to do
		}

		foreach ($transactions AS $transaction)
		{
			$lastOffset = $transaction->transaction_id;

			//			$transaction->setOption('log_moderator', false);
			$transaction->delete();

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				return $lastOffset; // continue at this position
			}
		}

		if ($fetchedTransactions == $maxFetch)
		{
			return $lastOffset; // more to do
		}

		return null;
	}
}
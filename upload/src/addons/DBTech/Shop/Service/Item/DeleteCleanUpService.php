<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Finder\PurchaseFinder;
use XF\App;
use XF\ContinuationResult;
use XF\MultiPartRunnerTrait;
use XF\PrintableException;
use XF\Service\AbstractService;

class DeleteCleanUpService extends AbstractService
{
	use MultiPartRunnerTrait;

	protected int $itemId;
	protected string $title;
	protected array $steps = [
		'stepDeletePurchases',
	];


	/**
	 * @param App $app
	 * @param int $itemId
	 * @param string $title
	 */
	public function __construct(App $app, int $itemId, string $title)
	{
		parent::__construct($app);

		$this->itemId = $itemId;
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
	protected function stepDeletePurchases(?int $lastOffset, float|int|null $maxRunTime): ?int
	{
		$start = microtime(true);

		/** @var \DBTech\Shop\Entity\Purchase[] $purchases */
		$finder = \XF::app()->finder(PurchaseFinder::class)
			->where('item_id', $this->itemId)
			->order('purchase_id');

		if ($lastOffset !== null)
		{
			$finder->where('purchase_id', '>', $lastOffset);
		}

		$maxFetch = 1000;
		$purchases = $finder->fetch($maxFetch);
		$fetchedPurchases = count($purchases);

		if (!$fetchedPurchases)
		{
			return null; // done or nothing to do
		}

		foreach ($purchases AS $purchase)
		{
			$lastOffset = $purchase->purchase_id;

			$purchase->setOption('log_moderator', false);
			$purchase->delete();

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				return $lastOffset; // continue at this position
			}
		}

		if ($fetchedPurchases == $maxFetch)
		{
			return $lastOffset; // more to do
		}

		return null;
	}
}
<?php

namespace DBTech\Shop\Service\Item;

/**
 * Class DeleteCleanUp
 *
 * @package DBTech\Shop\Service\Item
 */
class DeleteCleanUp extends \XF\Service\AbstractService
{
	use \XF\MultiPartRunnerTrait;
	
	/** @var int  */
	protected $itemId;

	/** @var string  */
	protected $title;
	
	/** @var array */
	protected $steps = [
		'stepDeletePurchases',
	];
	
	
	/**
	 * DeleteCleanUp constructor.
	 *
	 * @param \XF\App $app
	 * @param int $itemId
	 * @param string $title
	 */
	public function __construct(\XF\App $app, int $itemId, string $title)
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
	 * @param int $maxRunTime
	 *
	 * @return \XF\ContinuationResult
	 */
	public function cleanUp(int $maxRunTime = 0): \XF\ContinuationResult
	{
		$this->db()->beginTransaction();
		$result = $this->runLoop($maxRunTime);
		$this->db()->commit();

		return $result;
	}
	
	/**
	 * @param int|null $lastOffset
	 * @param int $maxRunTime
	 *
	 * @return int|null
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\PrintableException
	 */
	protected function stepDeletePurchases(?int $lastOffset, int $maxRunTime): ?int
	{
		$start = microtime(true);
		
		/** @var \DBTech\Shop\Entity\Purchase[] $purchases */
		$finder = $this->finder('DBTech\Shop:Purchase')
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
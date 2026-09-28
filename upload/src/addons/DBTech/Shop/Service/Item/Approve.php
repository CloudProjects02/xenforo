<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;

/**
 * Class Approve
 *
 * @package DBTech\Shop\Service\Item
 */
class Approve extends \XF\Service\AbstractService
{
	/** @var \DBTech\Shop\Entity\Item */
	protected $item;
	
	/** @var int */
	protected $notifyRunTime = 3;
	
	/**
	 * Approve constructor.
	 *
	 * @param \XF\App $app
	 * @param Item $item
	 */
	public function __construct(\XF\App $app, Item $item)
	{
		parent::__construct($app);
		$this->item = $item;
	}

	/**
	 * @return \DBTech\Shop\Entity\Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param int $time
	 *
	 * @return $this
	 */
	public function setNotifyRunTime(int $time): Approve
	{
		$this->notifyRunTime = $time;

		return $this;
	}
	
	/**
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function approve(): bool
	{
		if ($this->item->item_state == 'moderated')
		{
			$this->item->item_state = 'visible';
			$this->item->save();

			$this->onApprove();
			return true;
		}
		
		return false;
	}

	/**
	 *
	 */
	protected function onApprove(): void
	{
	}
}
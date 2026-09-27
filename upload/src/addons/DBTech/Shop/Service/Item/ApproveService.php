<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;

class ApproveService extends AbstractService
{
	protected Item $item;
	protected int $notifyRunTime = 3;

	/**
	 * @param App $app
	 * @param Item $item
	 */
	public function __construct(App $app, Item $item)
	{
		parent::__construct($app);
		$this->item = $item;
	}

	/**
	 * @return Item
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
	public function setNotifyRunTime(int $time): ApproveService
	{
		$this->notifyRunTime = $time;

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
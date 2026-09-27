<?php

namespace DBTech\Shop\Cron;

use DBTech\Shop\Repository\ItemRepository;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Db\Exception;
use XF\PrintableException;

class Item
{
	/**
	 * @throws PrintableException
	 */
	public static function duration(): void
	{
		$repo = \XF::app()->repository(PurchaseRepository::class);
		$repo->handleExpiredItems();
	}

	/**
	 *
	 */
	public static function autoBump(): void
	{
		$repo = \XF::app()->repository(PurchaseRepository::class);
		$repo->autoBumpThreads();
	}

	/**
	 * @throws Exception
	 */
	public static function refillStock(): void
	{
		$repo = \XF::app()->repository(ItemRepository::class);
		$repo->refillStock();
	}
}
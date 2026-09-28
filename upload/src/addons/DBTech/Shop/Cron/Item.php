<?php

namespace DBTech\Shop\Cron;

class Item
{
	/**
	 * @throws \XF\PrintableException
	 */
	public static function duration()
	{
		$repo = \XF::repository('DBTech\Shop:Purchase');
		$repo->handleExpiredItems();
	}
	
	/**
	 *
	 */
	public static function autoBump()
	{
		$repo = \XF::repository('DBTech\Shop:Purchase');
		$repo->autoBumpThreads();
	}
	
	/**
	 * @throws \XF\Db\Exception
	 */
	public static function refillStock()
	{
		$repo = \XF::repository('DBTech\Shop:Item');
		$repo->refillStock();
	}
}
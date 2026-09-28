<?php

namespace DBTech\Shop\Repository;

use XF\Repository\AbstractPrefix;

/**
 * Class ItemPrefix
 *
 * @package DBTech\Shop\Repository
 */
class ItemPrefix extends AbstractPrefix
{
	/**
	 * @return string
	 */
	protected function getRegistryKey(): string
	{
		return 'dbtShopPrefixes';
	}
	
	/**
	 * @return string
	 */
	protected function getClassIdentifier(): string
	{
		return 'DBTech\Shop:ItemPrefix';
	}
}
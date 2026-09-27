<?php

namespace DBTech\Shop\Repository;

use XF\Repository\AbstractPrefix;

class ItemPrefixRepository extends AbstractPrefix
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
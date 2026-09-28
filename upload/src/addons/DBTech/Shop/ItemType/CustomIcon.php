<?php

namespace DBTech\Shop\ItemType;

/**
 * Class CustomIcon
 *
 * @package DBTech\Shop\ItemType
 */
class CustomIcon extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}
}
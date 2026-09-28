<?php

namespace DBTech\Shop\ItemType;

/**
 * Class AvatarChange
 *
 * @package DBTech\Shop\ItemType
 */
class AvatarChange extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}
	
	/**
	 * @return bool
	 */
	public function canRevertConfiguration(): bool
	{
		return false;
	}
}
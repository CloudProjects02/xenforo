<?php

namespace DBTech\Shop\ItemType;

/**
 * Class ForumPassword
 *
 * @package DBTech\Shop\ItemType
 */
class ForumPassword extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}
}
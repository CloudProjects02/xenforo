<?php

namespace DBTech\Shop\ItemType;

/**
 * Class SignatureChange
 *
 * @package DBTech\Shop\ItemType
 */
class SignatureChange extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}
}
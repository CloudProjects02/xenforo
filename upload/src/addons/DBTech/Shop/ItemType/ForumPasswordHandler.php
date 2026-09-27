<?php

namespace DBTech\Shop\ItemType;

class ForumPasswordHandler extends AbstractHandler
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return false;
	}
}
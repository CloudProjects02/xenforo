<?php

namespace DBTech\Shop\NewsFeed;

use XF\NewsFeed\AbstractHandler;

class ItemHandler extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Permissions|' . $visitor->permission_combination_id, 'User', 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}
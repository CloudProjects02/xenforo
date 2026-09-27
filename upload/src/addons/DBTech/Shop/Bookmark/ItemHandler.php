<?php

namespace DBTech\Shop\Bookmark;

use XF\Bookmark\AbstractHandler;

class ItemHandler extends AbstractHandler
{
	/**
	 * @return string
	 */
	public function getCustomIconTemplateName(): string
	{
		return 'public:dbtech_shop_item_bookmark_custom_icon';
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id, 'User'];
	}
}
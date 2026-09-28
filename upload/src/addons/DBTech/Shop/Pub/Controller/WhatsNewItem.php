<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Pub\Controller\AbstractWhatsNewFindType;

/**
 * Class WhatsNewItem
 *
 * @package DBTech\Shop\Pub\Controller
 */
class WhatsNewItem extends AbstractWhatsNewFindType
{
	/**
	 * @return string
	 */
	protected function getContentType(): string
	{
		return 'dbtech_shop_item';
	}
}
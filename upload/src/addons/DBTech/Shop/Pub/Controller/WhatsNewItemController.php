<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Pub\Controller\AbstractWhatsNewFindType;

class WhatsNewItemController extends AbstractWhatsNewFindType
{
	/**
	 * @return string
	 */
	protected function getContentType(): string
	{
		return 'dbtech_shop_item';
	}
}
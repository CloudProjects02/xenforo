<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function getDisplayOrder(): int
	{
		return 210;
	}
}

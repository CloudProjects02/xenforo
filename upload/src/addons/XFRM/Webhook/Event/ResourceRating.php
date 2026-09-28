<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;

class ResourceRating extends AbstractHandler
{
	public function getDisplayOrder(): int
	{
		return 240;
	}
}

<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;

class ResourceUpdate extends AbstractHandler
{
	public function getDisplayOrder(): int
	{
		return 220;
	}
}

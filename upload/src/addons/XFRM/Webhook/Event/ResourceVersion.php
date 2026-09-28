<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;

class ResourceVersion extends AbstractHandler
{
	public function getDisplayOrder(): int
	{
		return 230;
	}
}

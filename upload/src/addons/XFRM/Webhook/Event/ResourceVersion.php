<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFRM\Webhook\Criteria\ResourceVersion as ResourceVersionCriteria;

class ResourceVersion extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return ResourceVersionCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 230;
	}
}

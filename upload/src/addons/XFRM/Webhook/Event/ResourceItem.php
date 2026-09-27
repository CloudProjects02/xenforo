<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFRM\Webhook\Criteria\ResourceItem as ResourceItemCriteria;

class ResourceItem extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return ResourceItemCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 210;
	}
}

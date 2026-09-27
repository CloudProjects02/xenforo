<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFRM\Webhook\Criteria\ResourceUpdate as ResourceUpdateCriteria;

class ResourceUpdate extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return ResourceUpdateCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 220;
	}
}

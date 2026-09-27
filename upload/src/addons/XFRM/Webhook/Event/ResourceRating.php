<?php

namespace XFRM\Webhook\Event;

use XF\Webhook\Event\AbstractHandler;
use XFRM\Webhook\Criteria\ResourceRating as ResourceRatingCriteria;

class ResourceRating extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return ResourceRatingCriteria::class;
	}

	public function getDisplayOrder(): int
	{
		return 240;
	}
}

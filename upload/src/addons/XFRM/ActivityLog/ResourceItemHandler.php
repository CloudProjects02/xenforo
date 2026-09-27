<?php

namespace XFRM\ActivityLog;

use XF\ActivityLog\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XFRM\Entity\ResourceItem;

/**
 * @extends AbstractHandler<ResourceItem>
 */
class ResourceItemHandler extends AbstractHandler
{
	protected function getReplyMetrics(Entity $content): array
	{
		return $this->getReplyMetricsSimple(
			'xf_rm_resource_rating',
			'rating_date',
			'resource_id = ?',
			[$content->getEntityId()]
		);
	}

	protected function getReactionMetrics(Entity $content): array
	{
		$description = $content->Description;
		if (!$description)
		{
			return [];
		}

		return parent::getReactionMetrics($description);
	}
}

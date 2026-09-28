<?php

namespace XFRM\ActivityLog;

use XF\ActivityLog\AbstractShimHandler;
use XF\Mvc\Entity\Entity;
use XFRM\Entity\ResourceUpdate;

use function in_array;

/**
 * @extends AbstractShimHandler<ResourceUpdate>
 */
class ResourceUpdateHandler extends AbstractShimHandler
{
	public function log(
		Entity $content,
		int $logDate,
		array $values,
		bool $increment
	): void
	{
		if (!$content->isDescription())
		{
			return;
		}

		$resource = $content->Resource;
		if (!$resource)
		{
			return;
		}

		$values = array_filter(
			$values,
			function ($type)
			{
				return in_array($type, ['reaction_count', 'reaction_score']);
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->getActivityLogRepo()->log(
			$resource,
			$logDate,
			$values,
			$increment
		);
	}
}

<?php

namespace XFRM\Job;

use XF\Job\AbstractImageOptimizationJob;
use XFRM\Entity\ResourceItem;
use XFRM\Service\ResourceItem\Icon;

class IconOptimization extends AbstractImageOptimizationJob
{
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit(
				'SELECT resource_id
					FROM xf_rm_resource
					WHERE resource_id > ?
						AND icon_date > 0
						AND icon_optimized = 0
					ORDER BY resource_id',
				$batch
			),
			$start
		);
	}

	protected function optimizeById($id): void
	{
		$resource = $this->app->find(ResourceItem::class, $id);
		$iconService = $this->app->service(Icon::class, $resource);
		$iconService->logIp(false);
		$iconService->optimizeExistingIcon();
	}

	protected function getStatusType(): string
	{
		return \XF::phrase('xfrm_resource_icons');
	}
}

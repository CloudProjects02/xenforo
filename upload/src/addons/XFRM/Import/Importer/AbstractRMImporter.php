<?php

namespace XFRM\Import\Importer;

use XF\Import\Importer\AbstractAddOnImporter;
use XF\Job\PermissionRebuild;
use XFRM\Job\Category;
use XFRM\Job\ResourceItem;
use XFRM\Job\UserResourceCount;

abstract class AbstractRMImporter extends AbstractAddOnImporter
{
	protected function isForumType($importType)
	{
		if ($importType === 'resource')
		{
			return false;
		}

		return (strpos($importType, 'resource_') !== 0);
	}

	public function canRetainIds()
	{
		$db = $this->app->db();

		$maxResourceId = $db->fetchOne("SELECT MAX(resource_id) FROM xf_rm_resource");
		if ($maxResourceId)
		{
			return false;
		}

		return true;
	}

	public function resetDataForRetainIds()
	{
		// category 1 is created by default in the installer so we need to remove that if retaining IDs
		$category = $this->em()->find(\XFRM\Entity\Category::class, 1);
		if ($category)
		{
			$category->delete();
		}
	}

	public function getFinalizeJobs(array $stepsRun)
	{
		$jobs = [];

		$jobs[] = Category::class;
		$jobs[] = ResourceItem::class;
		$jobs[] = UserResourceCount::class;
		$jobs[] = PermissionRebuild::class;

		return $jobs;
	}
}

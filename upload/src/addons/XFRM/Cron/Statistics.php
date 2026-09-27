<?php

namespace XFRM\Cron;

class Statistics
{
	public static function cacheResourceManagerStatistics(): void
	{
		$cache = \XF::app()->simpleCache()->XFRM;
		$db = \XF::db();

		$categoryCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_rm_category
		');

		$resourceCount = $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_rm_resource
			WHERE resource_state = \'visible\'
		');

		$downloadCount = $db->fetchOne('
			SELECT SUM(download_count)
			FROM xf_rm_resource
			WHERE resource_state = \'visible\'
		');

		$diskUsage = $db->fetchOne('
			SELECT SUM(attd.file_size)
			FROM xf_attachment_data AS attd
			INNER JOIN xf_attachment AS att ON
				(attd.data_id = att.data_id)
			LEFT JOIN xf_rm_resource_version AS rv ON
				(att.content_type = \'resource_version\' AND att.content_id = rv.resource_version_id)
			WHERE att.content_type = \'resource_version\'
			AND rv.version_state = \'visible\'
		');

		$cache->statisticsCache = [
			'category_count' => $categoryCount,
			'resource_count' => $resourceCount,
			'download_count' => $downloadCount,
			'disk_usage' => $diskUsage,
		];
	}
}

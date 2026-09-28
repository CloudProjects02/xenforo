<?php

namespace XFES\Repository;

use XF\Mvc\Entity\Repository;

class IndexFailed extends Repository
{
	public function findRetryableRecords()
	{
		return $this->finder('XFES:IndexFailed')
			->where('reindex_date', '<=', \XF::$time)
			->order('reindex_date');
	}

	public function logFailedIndexing($type, $id, ?array $record = null)
	{
		$action = $record ? 'index' : 'delete';

		if (!$record)
		{
			$record = [];
		}

		$this->db()->query("
			INSERT INTO xf_es_index_failed
				(content_type, content_id, action, data, fail_count, reindex_date)
			VALUES
				(?, ?, ?, ?, 0, ?)
			ON DUPLICATE KEY UPDATE
				action = VALUES(action),
				data = VALUES(data),
				fail_count = VALUES(fail_count),
				reindex_date = VALUES(reindex_date)
		", [$type, $id, $action, json_encode($record), \XF::$time]);
	}
}

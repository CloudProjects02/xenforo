<?php

namespace EAEAddons\ThreadCount\Job;

use XF\Job\AbstractRebuildJob;

class UserThreadCount extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			", $batch
		), $start);
	}

	protected function rebuildById($id)
	{
		$repo = $this->app->repository('EAEAddons\ThreadCount:Thread');
		$count = $repo->getUserThreadCount($id);

		$this->app->db()->update('xf_user', ['eaetc_thread_count' => $count], 'user_id = ?', $id);
	}

	protected function getStatusType()
	{
		return \XF::phrase('eae_tc_thread_counts');
	}
}
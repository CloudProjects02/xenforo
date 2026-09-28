<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;

/**
 * Class UserItemCount
 *
 * @package DBTech\Shop\Job
 */
class UserItemCount extends AbstractRebuildJob
{
	/**
	 * @param $start
	 * @param $batch
	 *
	 * @return array
	 */
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			'
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			',
			$batch
		), $start);
	}
	
	/**
	 * @param $id
	 */
	protected function rebuildById($id)
	{
		/** @var \DBTech\Shop\Repository\Item $repo */
		$repo = $this->app->repository('DBTech\Shop:Item');
		$count = $repo->getUserItemCount($id);

		$this->app->db()->update('xf_user', ['dbtech_shop_item_count' => $count], 'user_id = ?', $id);
	}
	
	/**
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_shop_item_counts');
	}
}
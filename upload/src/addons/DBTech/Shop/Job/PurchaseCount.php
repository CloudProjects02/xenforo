<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;

/**
 * Class PurchaseCount
 *
 * @package DBTech\Shop\Job
 */
class PurchaseCount extends AbstractRebuildJob
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
		/** @var \DBTech\Shop\Repository\Purchase $repo */
		$repo = $this->app->repository('DBTech\Shop:Purchase');
		$count = $repo->getPurchaseCount($id);

		$this->app->db()->update('xf_user', ['dbtech_shop_purchases' => $count], 'user_id = ?', $id);
	}

	/**
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_shop_purchase_counts');
	}
}
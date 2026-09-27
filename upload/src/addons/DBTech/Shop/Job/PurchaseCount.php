<?php

namespace DBTech\Shop\Job;

use DBTech\Shop\Repository\PurchaseRepository;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

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
		$db = \XF::app()->db();

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
	protected function rebuildById($id): void
	{
		$repo = \XF::app()->repository(PurchaseRepository::class);
		$count = $repo->getPurchaseCount($id);

		\XF::app()->db()->update('xf_user', ['dbtech_shop_purchases' => $count], 'user_id = ?', $id);
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('dbtech_shop_shop_purchase_counts');
	}
}
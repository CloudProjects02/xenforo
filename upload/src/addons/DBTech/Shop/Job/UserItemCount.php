<?php

namespace DBTech\Shop\Job;

use DBTech\Shop\Repository\ItemRepository;
use XF\Job\AbstractRebuildJob;
use XF\Phrase;

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
		$repo = \XF::app()->repository(ItemRepository::class);
		$count = $repo->getUserItemCount($id);

		\XF::app()->db()->update('xf_user', ['dbtech_shop_item_count' => $count], 'user_id = ?', $id);
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('dbtech_shop_shop_item_counts');
	}
}
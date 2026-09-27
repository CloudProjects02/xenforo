<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;
use XF\Phrase;
use XF\PrintableException;

class Item extends AbstractRebuildJob
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
				SELECT item_id
				FROM xf_dbtech_shop_item
				WHERE item_id > ?
				ORDER BY item_id
			',
			$batch
		), $start);
	}

	/**
	 * @param $id
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function rebuildById($id): void
	{
		$item = \XF::app()->em()->find(\DBTech\Shop\Entity\Item::class, $id);
		if ($item)
		{
			if ($item->rebuildCounters())
			{
				$item->save();
			}

			$item->rebuildItemFieldValuesCache();
		}
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('dbtech_shop_shop_items');
	}
}
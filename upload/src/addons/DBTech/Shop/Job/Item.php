<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;

/**
 * Class Item
 *
 * @package DBTech\Shop\Job
 */
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
		$db = $this->app->db();

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
	 * @throws \XF\PrintableException
	 */
	protected function rebuildById($id)
	{
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $this->app->em()->find('DBTech\Shop:Item', $id);
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
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_shop_items');
	}
}
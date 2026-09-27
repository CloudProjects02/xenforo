<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;
use XF\Phrase;
use XF\PrintableException;

class Category extends AbstractRebuildJob
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
				SELECT category_id
				FROM xf_dbtech_shop_category
				WHERE category_id > ?
				ORDER BY category_id
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
		$category = \XF::app()->em()->find(\DBTech\Shop\Entity\Category::class, $id);
		if ($category)
		{
			$category->rebuildCounters();
			$category->save();
		}
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('dbtech_shop_shop_categories');
	}
}
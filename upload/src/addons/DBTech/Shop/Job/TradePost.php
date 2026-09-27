<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;
use XF\Phrase;

class TradePost extends AbstractRebuildJob
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
			"
				SELECT trade_post_id
				FROM xf_dbtech_shop_trade_post
				WHERE trade_post_id > ?
				ORDER BY trade_post_id
			",
			$batch
		), $start);
	}

	/**
	 * @param $id
	 */
	protected function rebuildById($id): void
	{
		$tradePost = \XF::app()->em()->find(\DBTech\Shop\Entity\TradePost::class, $id);
		if (!$tradePost)
		{
			return;
		}

		$tradePost->rebuildCounters();
		$tradePost->saveIfChanged();
	}

	/**
	 * @return Phrase
	 */
	protected function getStatusType(): Phrase
	{
		return \XF::phrase('dbtech_shop_shop_trade_posts');
	}
}
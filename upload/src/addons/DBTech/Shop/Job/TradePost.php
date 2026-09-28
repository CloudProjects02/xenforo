<?php

namespace DBTech\Shop\Job;

use XF\Job\AbstractRebuildJob;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Job
 */
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
		$db = $this->app->db();

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
	protected function rebuildById($id)
	{
		/** @var \DBTech\Shop\Entity\TradePost $tradePost */
		$tradePost = $this->app->em()->find('DBTech\Shop:TradePost', $id);
		if (!$tradePost)
		{
			return;
		}

		$tradePost->rebuildCounters();
		$tradePost->saveIfChanged();
	}
	
	/**
	 * @return \XF\Phrase
	 */
	protected function getStatusType(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_shop_trade_posts');
	}
}
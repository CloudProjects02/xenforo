<?php

namespace DBTech\Shop\Stats;

use XF\Stats\AbstractHandler;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Stats
 */
class TradePost extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getStatsTypes(): array
	{
		return [
			'dbt_shop_trade_p' => \XF::phrase('dbtech_shop_trade_posts'),
			'dbt_shop_trade_p_reaction' => \XF::phrase('dbtech_shop_trade_post_reactions')
		];
	}
	
	/**
	 * @param $start
	 * @param $end
	 *
	 * @return array
	 */
	public function getData($start, $end): array
	{
		$db = $this->db();

		$tradePosts = $db->fetchPairs(
			$this->getBasicDataQuery('xf_dbtech_shop_trade_post', 'post_date', 'message_state = ?'),
			[$start, $end, 'visible']
		);

		$tradePostReactions = $db->fetchPairs(
			$this->getBasicDataQuery('xf_reaction_content', 'reaction_date', 'content_type = ? AND is_counted = ?'),
			[$start, $end, 'dbtech_shop_trade_post', 1]
		);

		return [
			'dbt_shop_trade_p' => $tradePosts,
			'dbt_shop_trade_p_reaction' => $tradePostReactions
		];
	}
}
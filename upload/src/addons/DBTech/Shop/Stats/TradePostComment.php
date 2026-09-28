<?php

namespace DBTech\Shop\Stats;

use XF\Stats\AbstractHandler;

/**
 * Class TradePostComment
 *
 * @package DBTech\Shop\Stats
 */
class TradePostComment extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getStatsTypes(): array
	{
		return [
			'dbt_shop_trade_c' => \XF::phrase('dbtech_shop_trade_post_comments'),
			'dbt_shop_trade_c_reaction' => \XF::phrase('dbtech_shop_trade_post_comment_reactions')
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

		$tradePostComments = $db->fetchPairs(
			$this->getBasicDataQuery('xf_dbtech_shop_trade_post_comment', 'comment_date', 'message_state = ?'),
			[$start, $end, 'visible']
		);

		$tradePostCommentReactions = $db->fetchPairs(
			$this->getBasicDataQuery('xf_reaction_content', 'reaction_date', 'content_type = ? AND is_counted = ?'),
			[$start, $end, 'dbtech_shop_trade_comment', 1]
		);

		return [
			'dbt_shop_trade_c' => $tradePostComments,
			'dbt_shop_trade_c_reaction' => $tradePostCommentReactions
		];
	}
}
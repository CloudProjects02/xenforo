<?php

namespace DBTech\Shop\Stats;

use XF\Stats\AbstractHandler;

/**
 * Class Item
 *
 * @package DBTech\Shop\Stats
 */
class Item extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getStatsTypes(): array
	{
		return [
			'dbt_shop_item' => \XF::phrase('dbtech_shop_items_added'),
			'dbt_shop_item_reaction' => \XF::phrase('dbtech_shop_item_reactions'),
			'dbt_shop_item_rating' => \XF::phrase('dbtech_shop_item_ratings'),
			'dbt_shop_purchase' => \XF::phrase('dbtech_shop_purchases'),
		];
	}
	
	/**
	 * @param int $start
	 * @param int $end
	 *
	 * @return array
	 */
	public function getData($start, $end): array
	{
		$db = $this->db();

		$items = $db->fetchPairs(
			$this->getBasicDataQuery('xf_dbtech_shop_item', 'creation_date', 'item_state = ?'),
			[$start, $end, 'visible']
		);

		$purchases = $db->fetchPairs(
			$this->getBasicDataQuery('xf_dbtech_shop_transaction_log', 'dateline', 'action = ?'),
			[$start, $end, 'purchase']
		);
		
		$itemReactions = $db->fetchPairs(
			$this->getBasicDataQuery('xf_reaction_content', 'reaction_date', 'content_type = ? AND is_counted = ?'),
			[$start, $end, 'dbtech_shop_item', 1]
		);
		
		$itemRatings = $db->fetchPairs(
			$this->getBasicDataQuery('xf_dbtech_shop_item_rating', 'rating_date', 'rating_state = ?'),
			[$start, $end, 'visible']
		);

		return [
			'dbt_shop_item' => $items,
			'dbt_shop_purchase' => $purchases,
			'dbt_shop_item_reaction' => $itemReactions,
			'dbt_shop_item_rating' => $itemRatings
		];
	}
}
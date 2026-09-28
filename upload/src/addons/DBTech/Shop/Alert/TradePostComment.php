<?php

namespace DBTech\Shop\Alert;

use XF\Alert\AbstractHandler;

/**
 * Class TradePostComment
 *
 * @package DBTech\Shop\Alert
 */
class TradePostComment extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['TradePost', 'TradePost.Trade'];
	}
	
	/**
	 * @return array
	 */
	public function getOptOutActions(): array
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->canViewDbtechShopTradePosts())
		{
			return [
				'your_trade',
				'your_post',
				'other_commenter',
				'reaction'
			];
		}
		else
		{
			return [];
		}
	}
	
	/**
	 * @return int
	 */
	public function getOptOutDisplayOrder(): int
	{
		return 90005;
	}
}
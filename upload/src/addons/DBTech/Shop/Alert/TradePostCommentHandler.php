<?php

namespace DBTech\Shop\Alert;

use DBTech\Shop\XF\Entity\User;
use XF\Alert\AbstractHandler;

class TradePostCommentHandler extends AbstractHandler
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
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->canViewDbtechShopTradePosts())
		{
			return [
				'your_trade',
				'your_post',
				'other_commenter',
				'reaction',
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
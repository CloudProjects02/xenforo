<?php

namespace DBTech\Shop\Alert;

use XF\Alert\AbstractHandler;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Alert
 */
class TradePost extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
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
				'insert',
				'mention',
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
		return 90000;
	}
}
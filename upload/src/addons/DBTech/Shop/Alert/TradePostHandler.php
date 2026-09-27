<?php

namespace DBTech\Shop\Alert;

use DBTech\Shop\XF\Entity\User;
use XF\Alert\AbstractHandler;

class TradePostHandler extends AbstractHandler
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
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->canViewDbtechShopTradePosts())
		{
			return [
				'insert',
				'mention',
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
		return 90000;
	}
}
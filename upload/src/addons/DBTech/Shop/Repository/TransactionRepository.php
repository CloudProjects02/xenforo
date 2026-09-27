<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Finder\TransactionLogFinder;
use XF\Mvc\Entity\Repository;
use XF\Phrase;

class TransactionRepository extends Repository
{
	/**
	 * @param array $limits
	 *
	 * @return TransactionLogFinder
	 * @throws \InvalidArgumentException
	 */
	public function findTransactionsForOverviewList(array $limits = []): TransactionLogFinder
	{
		$limits = array_replace([
			'visibility' => true,
		], $limits);

		/** @var TransactionLogFinder $transactionFinder */
		$transactionFinder = \XF::app()->finder(TransactionLogFinder::class);

		$transactionFinder
			->with('full')
			->useDefaultOrder();

		if ($limits['visibility'])
		{
			$transactionFinder->applyGlobalVisibilityChecks();
		}

		return $transactionFinder;
	}

	/**
	 * @param string $action
	 *
	 * @return Phrase
	 */
	public function getActionTitle(string $action): Phrase
	{
		$actions = $this->getActionTitlePairs();
		return $actions[$action] ?? \XF::phrase('dbtech_shop_unknown_action');
	}

	/**
	 * @return array
	 */
	public function getActionTitlePairs(): array
	{
		$arr = [
			'purchase'      => \XF::phrase('dbtech_shop_purchase'),
			'donate'        => \XF::phrase('dbtech_shop_donate'),
			'stealsuccess'  => \XF::phrase('dbtech_shop_stealsuccess'),
			'stealfail'     => \XF::phrase('dbtech_shop_stealfail'),
			'deposit'       => \XF::phrase('dbtech_shop_deposit'),
			'withdraw'      => \XF::phrase('dbtech_shop_withdraw'),
			'interest'      => \XF::phrase('dbtech_shop_interest'),
			'discard'       => \XF::phrase('dbtech_shop_discard'),
			'gift'          => \XF::phrase('dbtech_shop_gift'),
			'sale'          => \XF::phrase('dbtech_shop_sale'),
			'sellback'      => \XF::phrase('dbtech_shop_sellback'),
			'buyback'       => \XF::phrase('dbtech_shop_buyback'),
			'perreply'      => \XF::phrase('dbtech_shop_perreply'),
			'perthread'     => \XF::phrase('dbtech_shop_perthread'),
			'lotteryticket' => \XF::phrase('dbtech_shop_lotteryticket'),
			'lotteryprize'  => \XF::phrase('dbtech_shop_lotteryprize'),
			'pointsadjust'  => \XF::phrase('dbtech_shop_pointsadjust'),
			'trade'         => \XF::phrase('dbtech_shop_trade'),
			'transfer'      => \XF::phrase('dbtech_shop_transfer'),
		];

		asort($arr);

		return $arr;
	}
}
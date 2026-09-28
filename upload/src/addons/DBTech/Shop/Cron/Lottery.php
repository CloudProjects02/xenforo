<?php

namespace DBTech\Shop\Cron;

/**
 * Class Lottery
 *
 * @package DBTech\Shop\Cron
 */
class Lottery
{
	/**
	 * @throws \XF\PrintableException
	 */
	public static function draw()
	{
		if (!\XF::options()->dbtech_shop_lottery_enabled)
		{
			return;
		}
		
		/** @var \DBTech\Shop\Repository\Lottery $lotteryRepo */
		$lotteryRepo = \XF::repository('DBTech\Shop:Lottery');
		$lotteryRepo->drawLotteries();
	}
}
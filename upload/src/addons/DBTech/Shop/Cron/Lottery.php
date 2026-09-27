<?php

namespace DBTech\Shop\Cron;

use DBTech\Shop\Repository\LotteryRepository;
use XF\PrintableException;

class Lottery
{
	/**
	 * @throws PrintableException
	 */
	public static function draw(): void
	{
		if (!\XF::options()->dbtech_shop_lottery_enabled)
		{
			return;
		}

		$lotteryRepo = \XF::app()->repository(LotteryRepository::class);
		$lotteryRepo->drawLotteries();
	}
}
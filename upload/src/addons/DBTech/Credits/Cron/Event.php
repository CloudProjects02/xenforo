<?php

namespace DBTech\Credits\Cron;

use DBTech\Credits\Repository\EventTriggerRepository;

class Event
{
	/**
	 * @throws \Exception
	 */
	public static function birthday(): void
	{
		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
		$eventTriggerRepo->cronBirthday();
	}

	/**
	 * @throws \Exception
	 */
	public static function expiry(): void
	{
		\XF::app()->jobManager()->enqueueUnique(
			'dbtechCreditsExpiry',
			'DBTech\Credits:Expiry',
			[],
			false
		);
	}

	/**
	 * @throws \Exception
	 */
	public static function dailyCredits(): void
	{
		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		$daily = $eventTriggerRepo->getHandler('daily');
		$interest = $eventTriggerRepo->getHandler('interest');
		$taxation = $eventTriggerRepo->getHandler('taxation');
		$paycheck = $eventTriggerRepo->getHandler('paycheck');

		if ($daily->isActive() || $interest->isActive() || $taxation->isActive() || $paycheck->isActive())
		{
			\XF::app()->jobManager()->enqueueUnique(
				'dbtechCreditsDaily',
				'DBTech\Credits:DailyCredits',
				[],
				false
			);
		}
	}
}
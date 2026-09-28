<?php

namespace DBTech\Security\Cron;

use DBTech\Security\Repository\CountryRepository;
use XF\PrintableException;

class UpdateCountries
{
	/**
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public static function runUpdate(): void
	{
		$app = \XF::app();

		$countryRepo = \XF::app()->repository(CountryRepository::class);
		$countryRepo->updateCountryList();
	}

	/**
	 *
	 */
	public static function runIpUpdate(): void
	{
		$app = \XF::app();

		$app->jobManager()->enqueueUnique(
			'dbtechSecurityCountryBlockRebuild',
			'DBTech\Security:CountryBlockRebuild',
			[],
			false
		);
	}
}
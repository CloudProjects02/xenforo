<?php

namespace DBTech\Security\Job;

use DBTech\Security\Entity\Country;
use DBTech\Security\Repository\BanningRepository;
use DBTech\Security\Repository\CountryRepository;
use XF\Job\AbstractJob;
use XF\Job\JobResult;
use XF\PrintableException;

class CountryBlockRebuild extends AbstractJob
{
	/** @var array  */
	protected $defaultData = [
		'steps' => 0,
		'countryCodes' => [],
		'cleaned' => false,
	];


	/**
	 * @param int $maxRunTime
	 *
	 * @return JobResult
	 * @throws PrintableException
	 */
	public function run($maxRunTime): JobResult
	{
		$start = microtime(true);

		if (!$this->data['cleaned'])
		{
			$countryRepo = \XF::app()->repository(CountryRepository::class);

			$banningRepo = \XF::app()->repository(BanningRepository::class);

			$countries = $countryRepo->findCountriesForList()
				->fetch()
			;

			/** @var Country $country */
			foreach ($countries AS $country)
			{
				// Remove existing countries
				$banningRepo->removeBannedCountryIps($country->country_code);

				if ($country->blocked)
				{
					$this->data['countryCodes'][$country->country_code] = true;
				}
			}

			$this->data['cleaned'] = true;
		}

		if (empty($this->data['countryCodes']))
		{
			return $this->complete();
		}

		$this->data['steps']++;

		$db = \XF::app()->db();
		$countryCodes = $db->fetchAllColumn("
			SELECT country_code
			FROM xf_dbtech_security_country
			WHERE country_code IN(" . $db->quote(array_keys($this->data['countryCodes'])) . ")
			ORDER BY country_code
		");
		if (!$countryCodes)
		{
			return $this->complete();
		}

		$banningRepo = \XF::app()->repository(BanningRepository::class);

		foreach ($countryCodes AS $countryCode)
		{
			$banningRepo->updateAndBanIpsForCountry($countryCode);
			unset($this->data['countryCodes'][$countryCode]);

			if (microtime(true) - $start >= $maxRunTime)
			{
				break;
			}
		}

		return $this->resume();
	}

	/**
	 * @return string
	 */
	public function getStatusMessage(): string
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('dbtech_security_country_blocks');
		return sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat('. ', $this->data['steps']));
	}

	/**
	 * @return bool
	 */
	public function canCancel(): bool
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function canTriggerByChoice(): bool
	{
		return false;
	}
}
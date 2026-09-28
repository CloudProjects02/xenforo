<?php

namespace DBTech\Security\Repository;

use DBTech\Security\Entity\Country;
use DBTech\Security\Finder\CountryFinder;
use GuzzleHttp\Exception\RequestException;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class CountryRepository extends Repository
{
	/**
	 * @return CountryFinder
	 */
	public function findCountriesForList(): CountryFinder
	{
		return \XF::app()->finder(CountryFinder::class)
			->orderName();
	}

	/**
	 * @param string $countryCode
	 *
	 * @return CountryFinder
	 */
	public function findCountryByCode(string $countryCode): CountryFinder
	{
		return \XF::app()->finder(CountryFinder::class)
			->where('country_code', $countryCode);
	}

	/**
	 * @param bool $includeEmpty
	 * @param string|null $type
	 *
	 * @return array
	 */
	public function getCountryOptionsData(bool $includeEmpty = true, ?string $type = null): array
	{
		$choices = [];
		if ($includeEmpty)
		{
			$choices = [
				'' => ['value' => '', 'label' => \XF::phrase('(none)')],
			];
			if ($type !== null)
			{
				$choices['']['_type'] = $type;
			}
		}

		$countryList = $this->findCountriesForList();

		/** @var Country $entry */
		foreach ($countryList AS $entry)
		{
			$choices[$entry->country_code] = [
				'value' => $entry->country_code,
				'label' => $entry->name,
			];
			if ($type !== null)
			{
				$choices[$entry->country_code]['_type'] = $type;
			}
		}

		return $choices;
	}

	/**
	 * @param bool $includeEmpty
	 * @param bool $includeAll
	 *
	 * @return array
	 */
	public function getCountrySelectData(bool $includeEmpty = true, bool $includeAll = false): array
	{
		$choices = [];
		if ($includeEmpty)
		{
			$choices = [
				'' => \XF::phrase('(none)'),
			];
		}
		if ($includeAll)
		{
			$choices = [
				'-1' => \XF::phrase('(all)'),
			];
		}

		$countryList = $this->findCountriesForList();

		/** @var Country $entry */
		foreach ($countryList AS $entry)
		{
			$choices[$entry->country_code] = $entry->name;
		}

		return $choices;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function updateCountryList(): bool
	{
		$reader = $this->app()
			->http()
			->reader()
		;

		$countryList = null;

		try
		{
			/** @noinspection HttpUrlsUsage */
			$response = $reader->getUntrusted(
				'https://raw.githubusercontent.com/mledoze/countries/master/dist/countries.json'
			);
			if ($response)
			{
				$jsonText = $response->getBody()->getContents();

				$response->getBody()->close();

				if ($response->getStatusCode() == 200)
				{
					try
					{
						$countryList = \json_decode($jsonText, true);
					}
					catch (\InvalidArgumentException $e)
					{
						\XF::logException($e, false, 'Security error:');
						return false;
					}
				}
				else
				{
					\XF::logError(\XF::phraseDeferred('received_unexpected_response_code_x_message_y', [
						'code' => $response->getStatusCode(),
						'message' => $response->getReasonPhrase(),
					]));
					return false;
				}
			}
		}
		catch (RequestException $e)
		{
			\XF::logException($e, false, 'Security error:');
			return false;
		}

		if (!is_array($countryList))
		{
			return false;
		}

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\Country> $existingCountries */
		$existingCountries = $this->findCountriesForList()->fetch();

		$currentCountries = [];
		foreach ($countryList AS $country)
		{
			$nativeName = \reset($country['name']['native']);
			if ($nativeName === false)
			{
				$nativeName = $country['name'];
			}

			/** @var Country $newCountry */
			if (!isset($existingCountries[$country['cca2']]))
			{
				$newCountry = \XF::app()->em()->create(Country::class);
				$newCountry->bulkSet([
					'country_code' => $country['cca2'],
					'name' => $country['name']['common'],
					'native_name' => $nativeName['common'],
					'iso_code' => $country['cca3'],
				]);
				$newCountry->save();
			}
			else
			{
				$newCountry = $existingCountries[$country['cca2']];

				if ($newCountry->name != $country['name']['common'])
				{
					$newCountry->name = $country['name']['common'];
				}

				if ($newCountry->native_name != $nativeName['common'])
				{
					$newCountry->native_name = $nativeName['common'];
				}

				$newCountry->saveIfChanged();
			}
			$currentCountries[$country['cca2']] = $newCountry;
		}

		$missingCountries = array_diff_key($existingCountries->toArray(), $currentCountries);

		/** @var Country $country */
		foreach ($missingCountries AS $country)
		{
			$country->delete();
		}

		return true;
	}
}
<?php

namespace DBTech\Credits\Repository;

use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Entity\Event;
use DBTech\Credits\Entity\Transaction;
use DBTech\Credits\Finder\CurrencyFinder;
use DBTech\Credits\Finder\TransactionFinder;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use XF\Repository\OptionRepository;

class CurrencyRepository extends Repository
{
	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];

		/** @var AbstractCollection<Currency> $entities */
		$entities = \XF::app()->finder(CurrencyFinder::class)->fetch();
		foreach ($entities AS $entity)
		{
			$cache[$entity->getIdentifier()] = $entity->toArray(false);
		}

		return $cache;
	}

	/**
	 * @return array
	 */
	public function rebuildCache(): array
	{
		$cache = $this->getCacheData();
		\XF::registry()->set('dbtCreditsCurrencies', $cache);
		return $cache;
	}

	/**
	 * @return CurrencyFinder
	 */
	public function findCurrenciesForList(): CurrencyFinder
	{
		return \XF::app()->finder(CurrencyFinder::class)
			->orderForList()
		;
	}

	/**
	 * @return AbstractCollection<Currency>
	 */
	public function getCurrenciesFromContainer(): AbstractCollection
	{
		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			/** @var AbstractCollection<Currency> $currencies */
			return $currencies;
		}

		return \XF::app()->em()->getEmptyCollection();
	}

	/**
	 * @param AbstractCollection<Event> $events
	 *
	 * @return AbstractCollection<Currency>
	 */
	public function getCurrenciesFromEvents(
		AbstractCollection $events
	): AbstractCollection
	{
		$currencies = [];
		foreach ($events AS $event)
		{
			$currencies[$event->currency_id] = $event->Currency;
		}

		return new ArrayCollection($currencies);
	}

	/**
	 * @param AbstractCollection<Event> $events
	 * @param bool $onlyActive
	 *
	 * @return array
	 */
	public function getCurrencyTitlePairsFromEvents(
		AbstractCollection $events,
		bool $onlyActive = false
	): array
	{
		$currencies = [];
		foreach ($events AS $event)
		{
			$currencies[$event->currency_id] = $event->Currency;
		}

		return $this->getCurrencyTitlePairs($onlyActive, new ArrayCollection($currencies));
	}

	/**
	 * @param bool $onlyActive
	 * @param AbstractCollection<Currency>|null $currencies
	 *
	 * @return array
	 */
	public function getCurrencyTitlePairs(bool $onlyActive = false, ?AbstractCollection $currencies = null): array
	{
		if ($currencies === null)
		{
			$currencyFinder = $this->findCurrenciesForList();

			$currencies = $currencyFinder->fetch();
		}

		if ($onlyActive)
		{
			$currencies = $currencies->filterViewable();
		}

		if (!$currencies)
		{
			return [];
		}

		return $currencies->pluckNamed('title', 'currency_id');
	}

	/**
	 * @param bool $includeEmpty
	 * @param null $type
	 *
	 * @return array
	 */
	public function getCurrencyOptionsData(bool $includeEmpty = true, $type = null): array
	{
		$choices = [];
		if ($includeEmpty)
		{
			$choices = [
				0 => ['_type' => 'option', 'value' => 0, 'label' => \XF::phrase('(none)')],
			];
		}

		$currencies = $this->getCurrencyTitlePairs();

		foreach ($currencies AS $currencyId => $label)
		{
			$choices[$currencyId] = [
				'value' => $currencyId,
				'label' => $label,
			];
			if ($type !== null)
			{
				$choices[$currencyId]['_type'] = $type;
			}
		}

		return $choices;
	}

	/**
	 * @param bool $filterViewable
	 *
	 * @return AbstractCollection<Currency>
	 */
	public function getCurrencies(bool $filterViewable = false): AbstractCollection
	{
		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			/** @var AbstractCollection<Currency> $currencies */
			if ($filterViewable)
			{
				$currencies = $currencies->filterViewable();
			}

			return $currencies;
		}

		return \XF::app()->em()->getEmptyCollection();
	}

	/**
	 * @return AbstractCollection<Currency>
	 */
	public function getViewableCurrencies(): AbstractCollection
	{
		return $this->getCurrencies(true);
	}

	/**
	 * @return CurrencyFinder
	 */
	public function getDisplayCurrency(): CurrencyFinder
	{
		return \XF::app()->finder(CurrencyFinder::class)
			->where('is_display_currency', 1);
	}

	/**
	 * @return Currency
	 */
	public function getChargeCurrency(): Currency
	{
		$options = $this->options();
		$currencyId = $options->dbtech_credits_eventtrigger_content_currency;

		if (!$currencyId)
		{
			/** @var Currency $currency */
			$currency = \XF::app()->finder(CurrencyFinder::class)
				->fetchOne()
			;

			$optionRepo = \XF::app()->repository(OptionRepository::class);
			$optionRepo->updateOptions([
				'dbtech_credits_eventtrigger_content_currency' => $currency->currency_id,
			]);

			$currencyId = $currency->currency_id;
		}

		return \XF::app()->em()->find(Currency::class, $currencyId);
	}

	/**
	 * @param Currency $currency
	 * @param int $limit
	 *
	 * @return UserFinder
	 */
	public function getRichestUsers(Currency $currency, int $limit = 5): UserFinder
	{
		return \XF::app()->finder(UserFinder::class)
			->isValidUser()
			->order($currency->column, 'DESC')
			->limit($limit)
		;
	}

	/**
	 * @param AbstractCollection<Currency>|null $currencies
	 */
	public function resetCurrencies(?AbstractCollection $currencies = null): void
	{
		if ($currencies === null)
		{
			$currencies = $this->getCurrencies();
		}

		/** @var Currency $currency */
		foreach ($currencies AS $currency)
		{
			$this->db()->update('xf_user', [
				$currency->column => 0,
			], null);
		}
	}

	/**
	 * @param int $userId
	 * @param int $currencyId
	 *
	 * @return float
	 */
	public function getUserBalanceFromTransactionLog(int $userId, int $currencyId): float
	{
		/** @var Transaction $latestTransaction */
		$latestTransaction = \XF::app()->finder(TransactionFinder::class)
			->where('user_id', $userId)
			->where('currency_id', $currencyId)
			->order('dateline', 'desc')
			->fetchOne()
		;
		if (!$latestTransaction)
		{
			return 0.00;
		}

		return $latestTransaction->balance;
	}
}
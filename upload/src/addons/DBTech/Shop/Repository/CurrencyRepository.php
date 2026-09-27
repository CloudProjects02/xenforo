<?php

namespace DBTech\Shop\Repository;

use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Entity\TransactionLog;
use DBTech\Shop\Finder\BankFinder;
use DBTech\Shop\Finder\CurrencyFinder;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Repository\IpRepository;

class CurrencyRepository extends Repository
{
	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency> $entities */
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
		\XF::registry()->set('dbtShopCurrencies', $cache);
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
	 * @param bool $onlyActive
	 *
	 * @return array|ArrayCollection
	 */
	public function getCurrencyTitlePairs(bool $onlyActive = false): ArrayCollection|array
	{
		$currencyFinder = $this->findCurrenciesForList();

		$currencies = $currencyFinder->fetch();
		if ($onlyActive)
		{
			$currencies = $currencies->filterViewable();
		}

		return $currencies->pluckNamed('title', 'currency_id');
	}

	/**
	 * @param bool $includeEmpty
	 * @param string|null $type
	 *
	 * @return array
	 */
	public function getCurrencyOptionsData(bool $includeEmpty = true, ?string $type = null): array
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
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency>
	 */
	public function getCurrencies(bool $filterViewable = false): AbstractCollection
	{
		$container = \XF::app()->container();
		if (isset($container['dbtechShop.currencies']) && $currencies = $container['dbtechShop.currencies'])
		{
			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency> $currencies */
			if ($filterViewable)
			{
				$currencies = $currencies->filterViewable();
			}

			return $currencies;
		}

		return \XF::app()->em()->getEmptyCollection();
	}

	/**
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency>
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
	 * @return Currency|null
	 */
	public function getDisplayCurrencyFromCache(): ?Currency
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency> $currencies */
		$container = \XF::app()->container();
		if (isset($container['dbtechShop.currencies']) && $currencies = $container['dbtechShop.currencies'])
		{
			/** @var Currency $currency */
			foreach ($currencies AS $currency)
			{
				if ($currency->is_display_currency)
				{
					return $currency;
				}
			}
		}

		return null;
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
			->limit($limit);
	}

	/**
	 * @param Currency $currency
	 * @param string $action
	 * @param float $amount
	 * @param User $user
	 * @param string $contentType
	 * @param string $contentId
	 * @param string|null $referenceId
	 * @param bool $negate
	 * @param bool $logIp
	 *
	 * @throws PrintableException
	 * @throws \LogicException
	 */
	public function addCurrencyAmount(
		Currency $currency,
		string $action,
		float $amount,
		User $user,
		string $contentType = '',
		string $contentId = '',
		?string $referenceId = null,
		bool $negate = false,
		bool $logIp = false
	): void
	{
		$addOns = \XF::app()->container('addon.cache');
		if ($currency->isIntegrated() && array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			try
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
				$actionHandler = $eventTriggerRepo->getHandler('shop_' . $action);

				$func = $negate ? 'undo' : 'apply';

				$actionHandler->$func($referenceId ?: $currency->currency_id, [
					'multiplier' => $amount,
					'currency_id' => $currency->credits_currency_id,
					'content_type' => $contentType,
					'content_id' => $contentId,
				], $user);

				return;
			}
			catch (\Exception $e)
			{
				\XF::logException($e);
				throw new \LogicException("Attempted to update currency amount for a currency integrated with DragonByte Credits.");
			}
		}

		$db = $this->db();

		$db->beginTransaction();

		$user->fastUpdate($currency->column, doubleval($user->{$currency->column}) + $amount);
		$this->logTransaction($currency, $action, $amount, $user, $contentType, $contentId, $logIp);

		$db->commit();
	}

	/**
	 * @param Currency $currency
	 * @param string $action
	 * @param float $amount
	 * @param User $user
	 * @param string $contentType
	 * @param string $contentId
	 * @param string|null $referenceId
	 * @param bool $negate
	 * @param bool $logIp
	 *
	 * @throws PrintableException
	 * @throws \LogicException
	 */
	public function removeCurrencyAmount(
		Currency $currency,
		string $action,
		float $amount,
		User $user,
		string $contentType = '',
		string $contentId = '',
		?string $referenceId = null,
		bool $negate = false,
		bool $logIp = false
	): void
	{
		$addOns = \XF::app()->container('addon.cache');
		if ($currency->isIntegrated() && array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			try
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
				$actionHandler = $eventTriggerRepo->getHandler('shop_' . $action);

				$func = $negate ? 'undo' : 'apply';

				$actionHandler->$func($referenceId ?: $currency->currency_id, [
					'multiplier' => $negate ? $amount : (-1 * $amount),
					'currency_id' => $currency->credits_currency_id,
					'content_type' => $contentType,
					'content_id' => $contentId,
				], $user);

				return;
			}
			catch (\Exception $e)
			{
				\XF::logException($e);
				throw new \LogicException("Attempted to update currency amount for a currency integrated with DragonByte Credits.");
			}
		}

		$db = $this->db();

		$db->beginTransaction();

		$user->fastUpdate($currency->column, doubleval($user->{$currency->column}) - $amount);
		$this->logTransaction($currency, $action, ($amount * -1), $user, $contentType, $contentId, $logIp);

		$db->commit();
	}

	/**
	 * @param Currency $currency
	 * @param User $user
	 * @param float $amount
	 * @param bool $logIp
	 *
	 * @throws PrintableException
	 */
	public function setCurrencyAmount(
		Currency $currency,
		User $user,
		float $amount,
		bool $logIp = false
	): void
	{
		$addOns = \XF::app()->container('addon.cache');
		if ($currency->isIntegrated() && array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			try
			{
				$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
				$adjustHandler = $eventTriggerRepo->getHandler('adjust');

				if ($user->{$currency->column} < $amount)
				{
					// Adjust event (up)
					$adjustHandler
						->apply($user->user_id, [
							'currency_id' 	=> $currency->credits_currency_id,
							'multiplier' 	=> abs($amount - $user->{$currency->column}),
							'message'  		=> \XF::language()->renderPhrase('dbtech_shop_shop_adjust'),
							'source_user_id' => $user->user_id,
						], $user);
				}
				else if ($user->{$currency->column} > $amount)
				{
					// Adjust event (down)
					$adjustHandler
						->apply($user->user_id, [
							'currency_id' => $currency->credits_currency_id,
							'multiplier' => (-1 * abs($user->{$currency->column} - $amount)),
							'message' => \XF::language()->renderPhrase('dbtech_shop_shop_adjust'),
							'source_user_id' => $user->user_id,
						], $user);
				}

				$this->logTransaction($currency, 'adjust', $amount, $user, $logIp);

				return;
			}
			catch (\Exception $e)
			{
				\XF::logException($e);
				throw new \LogicException("Attempted to update currency amount for a currency integrated with DragonByte Credits.");
			}
		}

		$db = $this->db();

		$db->beginTransaction();

		$user->fastUpdate($currency->column, $amount);
		$this->logTransaction($currency, 'adjust', $amount, $user, $logIp);

		$db->commit();
	}

	/**
	 * @param Currency $currency
	 * @param string $action
	 * @param float $amount
	 * @param User $user
	 * @param string $contentType
	 * @param string $contentId
	 * @param bool $logIp
	 *
	 * @throws PrintableException
	 * @throws \LogicException
	 */
	public function logTransaction(
		Currency $currency,
		string $action,
		float $amount,
		User $user,
		string $contentType = '',
		string $contentId = '',
		bool $logIp = false
	): void
	{
		$transaction = \XF::app()->em()->create(TransactionLog::class);
		$transaction->user_id = $user->user_id;
		$transaction->recipient_user_id = $user->user_id;
		$transaction->action = $action;
		if ($contentType && $contentId)
		{
			$transaction->content_type = $contentType;
			$transaction->content_id = $contentId;
		}
		$transaction->info = [
			'currencyid' 	=> $currency->currency_id,
			'amount' 		=> $amount,
			'content_id' 	=> $contentId,
		];
		$transaction->save(true, false);

		if ($logIp)
		{
			$ipRepo = \XF::app()->repository(IpRepository::class);
			$ipEnt = $ipRepo->logIp(
				$user->user_id,
				\XF::app()->request()->getIp(),
				'dbtech_shop_transaction',
				$transaction->transaction_log_id
			);
			if ($ipEnt)
			{
				$transaction->fastUpdate('ip_id', $ipEnt->ip_id);
			}
		}
	}

	/**
	 * @param bool $onlyWithInterest
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency>
	 */
	public function getBankableCurrencyList(bool $onlyWithInterest = false): AbstractCollection
	{
		$finder = $this->findCurrenciesForList();
		if ($onlyWithInterest)
		{
			$finder->where('interest', '>', 0);
		}

		return $finder->fetch()
			->filter(function (Currency $currency): ?Currency
			{
				if (!$currency->canBank())
				{
					return null;
				}

				return $currency;
			})
		;
	}

	/**
	 * @param User|null $user
	 *
	 * @return BankFinder
	 */
	public function findBankedCurrenciesForUser(?User $user = null): BankFinder
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */
		$user = $user ?: \XF::visitor();

		return \XF::app()->finder(BankFinder::class)
			->with('Currency')
			->where('user_id', $user->user_id)
			->keyedBy('currency_id');
	}

	/**
	 * @return BankFinder
	 */
	public function findBankedCurrencies(): BankFinder
	{
		return \XF::app()->finder(BankFinder::class)
			->where('last_interest_date', '<=', time() - 86400)
			->where('points', '>', 0);
	}
}
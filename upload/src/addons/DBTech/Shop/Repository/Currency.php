<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Currency
 * @package DBTech\Shop\Repository
 */
class Currency extends Repository
{
	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];
		
		/** @var \DBTech\Shop\Entity\Currency[] $entities */
		$entities = $this->finder('DBTech\Shop:Currency')->fetch();
		foreach ($entities as $entity)
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
	 * @return Finder
	 */
	public function findCurrenciesForList(): Finder
	{
		/** @var \DBTech\Shop\Finder\Currency $finder */
		$finder = $this->finder('DBTech\Shop:Currency');
		
		return $finder->orderForList();
	}
	
	/**
	 * @param bool $onlyActive
	 *
	 * @return array|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getCurrencyTitlePairs(bool $onlyActive = false)
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
				0 => ['_type' => 'option', 'value' => 0, 'label' => \XF::phrase('(none)')]
			];
		}
		
		$currencies = $this->getCurrencyTitlePairs();
		
		foreach ($currencies AS $currencyId => $label)
		{
			$choices[$currencyId] = [
				'value' => $currencyId,
				'label' => $label
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
	 * @return \DBTech\Shop\Entity\Currency[]|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getCurrencies(bool $filterViewable = false)
	{
		$container = $this->app()->container();
		if (isset($container['dbtechShop.currencies']) && $currencies = $container['dbtechShop.currencies'])
		{
			if ($filterViewable)
			{
				$currencies = $currencies->filterViewable();
			}
			
			return $currencies;
		}
		
		return $this->em->getEmptyCollection();
	}
	
	/**
	 * @return \DBTech\Shop\Entity\Currency[]|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getViewableCurrencies()
	{
		return $this->getCurrencies(true);
	}
	
	/**
	 * @return Finder
	 */
	public function getDisplayCurrency(): Finder
	{
		return $this->finder('DBTech\Shop:Currency')
			->where('is_display_currency', 1);
	}
	
	/**
	 * @return \DBTech\Shop\Entity\Currency|null
	 */
	public function getDisplayCurrencyFromCache(): ?\DBTech\Shop\Entity\Currency
	{
		/** @var \XF\Mvc\Entity\ArrayCollection $currencies */
		$container = \XF::app()->container();
		if (isset($container['dbtechShop.currencies']) && $currencies = $container['dbtechShop.currencies'])
		{
			/** @var \DBTech\Shop\Entity\Currency $currency */
			foreach ($currencies as $currency)
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
	 * @param \DBTech\Shop\Entity\Currency $currency
	 * @param int $limit
	 *
	 * @return Finder
	 */
	public function getRichestUsers(\DBTech\Shop\Entity\Currency $currency, int $limit = 5): Finder
	{
		return $this->finder('XF:User')
			->isValidUser()
			->order($currency->column, 'DESC')
			->limit($limit);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Currency $currency
	 * @param string $action
	 * @param float $amount
	 * @param \XF\Entity\User $user
	 * @param string $contentType
	 * @param string $contentId
	 * @param string|null $referenceId
	 * @param bool $negate
	 * @param bool $logIp
	 *
	 * @throws \XF\PrintableException
	 * @throws \LogicException
	 */
	public function addCurrencyAmount(
		\DBTech\Shop\Entity\Currency $currency,
		string $action,
		float $amount,
		\XF\Entity\User $user,
		string $contentType = '',
		string $contentId = '',
		?string $referenceId = null,
		bool $negate = false,
		bool $logIp = false
	) {
		$addOns = \XF::app()->container('addon.cache');
		if ($currency->isIntegrated() && array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			try
			{
				/** @var \DBTech\Credits\Repository\EventTrigger $eventTriggerRepo */
				$eventTriggerRepo = \XF::repository('DBTech\Credits:EventTrigger');
				$actionHandler = $eventTriggerRepo->getHandler('shop_' . $action);
				
				$func = $negate ? 'undo' : 'apply';
				
				$actionHandler->$func($referenceId ?: $currency->currency_id, [
					'multiplier' => $amount,
					'currency_id' => $currency->credits_currency_id,
					'content_type' => $contentType,
					'content_id' => $contentId
				], $user);
				
				return;
			}
			catch (\Exception $e)
			{
				throw new \LogicException("Attempted to update currency amount for a currency integrated with DragonByte Credits.");
			}
		}
		
		$db = $this->db();
		
		$db->beginTransaction();
		
		$user->fastUpdate($currency->column, doubleval($user->{$currency->column}) + doubleval($amount));
		$this->logTransaction($currency, $action, $amount, $user, $contentType, $contentId, $logIp);
		
		$db->commit();
	}

	/**
	 * @param \DBTech\Shop\Entity\Currency $currency
	 * @param string $action
	 * @param float $amount
	 * @param \XF\Entity\User $user
	 * @param string $contentType
	 * @param string $contentId
	 * @param string|null $referenceId
	 * @param bool $negate
	 * @param bool $logIp
	 *
	 * @throws \XF\PrintableException
	 * @throws \LogicException
	 */
	public function removeCurrencyAmount(
		\DBTech\Shop\Entity\Currency $currency,
		string $action,
		float $amount,
		\XF\Entity\User $user,
		string $contentType = '',
		string $contentId = '',
		?string $referenceId = null,
		bool $negate = false,
		bool $logIp = false
	) {
		$addOns = \XF::app()->container('addon.cache');
		if ($currency->isIntegrated() && array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			try
			{
				/** @var \DBTech\Credits\Repository\EventTrigger $eventTriggerRepo */
				$eventTriggerRepo = \XF::repository('DBTech\Credits:EventTrigger');
				$actionHandler = $eventTriggerRepo->getHandler('shop_' . $action);
				
				$func = $negate ? 'undo' : 'apply';
				
				$actionHandler->$func($referenceId ?: $currency->currency_id, [
					'multiplier' => $negate ? $amount : (-1 * $amount),
					'currency_id' => $currency->credits_currency_id,
					'content_type' => $contentType,
					'content_id' => $contentId
				], $user);
				
				return;
			}
			catch (\Exception $e)
			{
				throw new \LogicException("Attempted to update currency amount for a currency integrated with DragonByte Credits.");
			}
		}
		
		$db = $this->db();
		
		$db->beginTransaction();
		
		$user->fastUpdate($currency->column, doubleval($user->{$currency->column}) - doubleval($amount));
		$this->logTransaction($currency, $action, (doubleval($amount) * -1), $user, $contentType, $contentId, $logIp);
		
		$db->commit();
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Currency $currency
	 * @param \XF\Entity\User $user
	 * @param float $amount
	 * @param bool $logIp
	 *
	 * @throws \XF\PrintableException
	 */
	public function setCurrencyAmount(
		\DBTech\Shop\Entity\Currency $currency,
		\XF\Entity\User $user,
		float $amount,
		bool $logIp = false
	) {
		$addOns = \XF::app()->container('addon.cache');
		if ($currency->isIntegrated() && array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			try
			{
				/** @var \DBTech\Credits\Repository\EventTrigger $eventTriggerRepo */
				$eventTriggerRepo = \XF::repository('DBTech\Credits:EventTrigger');
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
				elseif ($user->{$currency->column} > $amount)
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
	 * @param \DBTech\Shop\Entity\Currency $currency
	 * @param string $action
	 * @param float $amount
	 * @param \XF\Entity\User $user
	 * @param string $contentType
	 * @param string $contentId
	 * @param bool $logIp
	 *
	 * @throws \XF\PrintableException
	 * @throws \LogicException
	 */
	public function logTransaction(
		\DBTech\Shop\Entity\Currency $currency,
		string $action,
		float $amount,
		\XF\Entity\User $user,
		string $contentType = '',
		string $contentId = '',
		bool $logIp = false
	) {
		$transaction = $this->em->create('DBTech\Shop:TransactionLog');
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
			'amount' 		=> doubleval($amount),
			'content_id' 	=> $contentId
		];
		$transaction->save(true, false);
		
		if ($logIp)
		{
			/** @var \XF\Repository\IP $ipRepo */
			$ipRepo = $this->repository('XF:Ip');
			$ipEnt = $ipRepo->logIp($user->user_id, $this->app()->request()->getIp(), 'dbtech_shop_transaction', $transaction->transaction_log_id);
			if ($ipEnt)
			{
				$transaction->fastUpdate('ip_id', $ipEnt->ip_id);
			}
		}
	}
	
	/**
	 * @param bool $onlyWithInterest
	 *
	 * @return ArrayCollection
	 */
	public function getBankableCurrencyList(bool $onlyWithInterest = false): ArrayCollection
	{
		$finder = $this->findCurrenciesForList();
		if ($onlyWithInterest)
		{
			$finder->where('interest', '>', 0);
		}
		
		/** @var \DBTech\Shop\Entity\Currency[]|ArrayCollection $currencies */
		return $finder->fetch()
			->filter(function (\DBTech\Shop\Entity\Currency $currency): ?\DBTech\Shop\Entity\Currency
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
	 * @param \XF\Entity\User|null $user
	 *
	 * @return Finder
	 */
	public function findBankedCurrenciesForUser(?\XF\Entity\User $user = null): Finder
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */
		$user = $user ?: \XF::visitor();
		
		return $this->finder('DBTech\Shop:Bank')
			->with('Currency')
			->where('user_id', $user->user_id)
			->keyedBy('currency_id');
	}
	
	/**
	 * @return Finder
	 */
	public function findBankedCurrencies(): Finder
	{
		return $this->finder('DBTech\Shop:Bank')
			->where('last_interest_date', '<=', time() - 86400)
			->where('points', '>', 0);
	}
}
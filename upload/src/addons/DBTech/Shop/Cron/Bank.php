<?php

namespace DBTech\Shop\Cron;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Finder\UserFinder;
use XF\PrintableException;

class Bank
{
	/**
	 * @throws PrintableException
	 */
	public static function calculateInterest(): void
	{
		$options = \XF::options();

		if (!$options->dbtech_shop_bank_enabled || $options->dbtech_shop_manualinterest)
		{
			return;
		}

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);
		$currencies = $currencyRepo->getBankableCurrencyList();
		if (!$currencies->count())
		{
			return;
		}

		$users = \XF::app()->finder(UserFinder::class)
			->isValidUser()
			->isRecentlyActive($options->dbtechShopInterestMinimumActivity)
			->fetch();

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Bank> $banked */
		$banked = $currencyRepo->findBankedCurrencies()
			->where('user_id', $users->keys())
			->where('currency_id', $currencies->keys())
			->fetch()
		;
		foreach ($banked AS $bank)
		{
			/** @var Currency $currency */
			$currency = $currencies->offsetGet($bank->currency_id);
			$bank->hydrateRelation('Currency', $currency);

			/** @var User $user */
			$user = $users->offsetGet($bank->user_id);
			$bank->hydrateRelation('User', $user);

			if ($bank->collectInterest(true))
			{
				$bank->save();

				$currencyRepo->logTransaction(
					$currency,
					'interest',
					$bank->points - $bank->getPreviousValue('points'),
					$user
				);
			}
		}
	}
}
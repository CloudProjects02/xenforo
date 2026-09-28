<?php

namespace DBTech\Shop\Cron;

class Bank
{
	/**
	 * @throws \XF\PrintableException
	 */
	public static function calculateInterest()
	{
		$options = \XF::options();
		
		if (!$options->dbtech_shop_bank_enabled || $options->dbtech_shop_manualinterest)
		{
			return;
		}
		
		/** @var \DBTech\Shop\Repository\Currency $currencyRepo */
		$currencyRepo = \XF::repository('DBTech\Shop:Currency');
		$currencies = $currencyRepo->getBankableCurrencyList();
		if (!$currencies)
		{
			return;
		}
		
		/** @var \XF\Finder\User $userFinder */
		$userFinder = \XF::finder('XF:User');
		
		$users = $userFinder
			->isValidUser(false)
			->isRecentlyActive($options->dbtechShopInterestMinimumActivity)
			->fetch();
		
		/** @var \DBTech\Shop\Entity\Bank[]|\XF\Mvc\Entity\ArrayCollection $banked */
		$banked = $currencyRepo->findBankedCurrencies()
			->where('user_id', $users->keys())
			->where('currency_id', $currencies->keys())
			->fetch()
		;
		foreach ($banked as $bank)
		{
			/** @var \DBTech\Shop\Entity\Currency $currency */
			$currency = $currencies->offsetGet($bank->currency_id);
			$bank->hydrateRelation('Currency', $currency);
			
			/** @var \DBTech\Shop\XF\Entity\User $user */
			$user = $users->offsetGet($bank->user_id);
			$bank->hydrateRelation('User', $user);
			
			if ($bank->collectInterest(true))
			{
				$bank->save();
				
				$currencyRepo->logTransaction(
					$currency,
					'interest',
					$bank->points - $bank->getPreviousValue('points'),
					$user,
					'',
					'',
					false
				);
			}
		}
	}
}
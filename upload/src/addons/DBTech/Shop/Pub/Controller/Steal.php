<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Steal
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Steal extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!$this->options()->dbtech_shop_steal_enabled)
		{
			throw $this->exception($this->notFound());
		}
		
		$this->assertRegistrationRequired();
		
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}
		
		if (!$visitor->canUseDbtechShopSteal($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}
	
	/**
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionIndex()
	{
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/steal'));
		
		$currencies = $this->getCurrencyRepo()
			->findCurrenciesForList()
			->fetch()
			->filterViewable()
			->filter(function (\DBTech\Shop\Entity\Currency $currency): ?\DBTech\Shop\Entity\Currency
			{
				if (!$currency->canSteal())
				{
					return null;
				}
				
				return $currency;
			})
		;
		
		if (!$currencies->count())
		{
			return $this->error(
				\XF::phraseDeferred('dbtech_shop_no_currency_available_to_steal')
			);
		}
		
		$stealChance = $this->options()->dbtech_shop_steal_chance;
		$stealAmount = $this->options()->dbtech_shop_steal_amount;
		
		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = $this->repository('DBTech\Shop:Purchase')
			->filterActivePurchasesForUser(\XF::visitor())
		;
		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('steal_chance', [&$stealChance]);
			$handler->fire('steal_amount', [&$stealAmount]);
		}
		
		if ($this->isPost())
		{
			$visitor = \XF::visitor();
			
			$currencyId = $this->filter('currency_id', 'uint');
			$userName = $this->filter('username', 'str');
			
			if (!$currencies->offsetExists($currencyId))
			{
				return $this->error(
					\XF::phraseDeferred('dbtech_shop_no_currency_could_be_found_with_id_x', [
						'currency_id' => $currencyId
					])
				);
			}
			
			/** @var \DBTech\Shop\Entity\Currency $currency */
			$currency = $currencies->offsetGet($currencyId);

			if ($this->options()->dbtechShopMininumStealWallet
				&& $currency->getValueFromUser($visitor, false) <= $this->options()->dbtechShopMininumStealWallet
			) {
				return $this->error(\XF::phraseDeferred('dbtech_shop_need_x_y_to_steal', [
					'amount' => $currency->getFormattedValue($this->options()->dbtechShopMininumStealWallet),
					'currency' => $currency->title
				]));
			}
			
			/** @var \XF\Entity\User $user */
			$user = $this->em()->findOne('XF:User', ['username' => $userName]);
			if (!$user)
			{
				return $this->error(\XF::phraseDeferred('requested_user_x_not_found', ['name' => $userName]));
			}

			/*
			if ($user->user_id == $visitor->user_id)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_cannot_steal_from_yourself'));
			}
			*/
			
			$retval = false;
			
			/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
			$purchases = $this->repository('DBTech\Shop:Purchase')
				->filterActivePurchasesForUser($user)
			;
			foreach ($purchases as $purchase)
			{
				$handler = $purchase->handler;
				$handler->fire('immunity', ['theft', &$retval]);
			}
			
			if ($retval)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_steal_target_immune'));
			}
			
			if ($stealChance >= 100 || mt_rand(1, 100) <= $stealChance)
			{
				$points = ($currency->getValueFromUser($user, false) * ($stealAmount / 100));
				
				// Remove points from steal target
				$this->getCurrencyRepo()
					->removeCurrencyAmount(
						$currency,
						'stealsuccess',
						$points,
						$user
					)
				;
				
				// Add points to current user
				$this->getCurrencyRepo()
					->addCurrencyAmount(
						$currency,
						'stealsuccess',
						$points,
						$visitor
					)
				;
				
				if (!$currency->isIntegrated())
				{
					$extra = [
						'user' => $visitor->username,
						'link' => $this->app()->router('public')->buildLink('nopath:members', $visitor),
						'currency' => $currency->title,
						'amount_stolen' => $currency->prefix . $currency->getFormattedValue($points) . $currency->suffix,
						'depends_on_addon_id' => 'DBTech/Shop',
					];
					
					/** @var \XF\Repository\UserAlert $alertRepo */
					$alertRepo = $this->repository('XF:UserAlert');
					$alertRepo->alert(
						$user,
						$visitor->user_id,
						$visitor->username,
						'user',
						$visitor->user_id,
						"dbt_shop_steal_success",
						$extra
					);
				}
				
				return $this->redirect(
					$this->buildLink('dbtech-shop/steal', null, ['success' => true]),
					\XF::phrase('dbtech_shop_funds_stolen_successfully')
				);
			}
			else
			{
				$stealPenalty = $this->options()->dbtech_shop_steal_lose;
				
				/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
				$purchases = $this->repository('DBTech\Shop:Purchase')
					->filterActivePurchasesForUser($user)
				;
				foreach ($purchases as $purchase)
				{
					$handler = $purchase->handler;
					$handler->fire('steal_penalty', [&$stealPenalty]);
				}
				
				if ($stealPenalty)
				{
					$points = (
						$currency->getValueFromUser($visitor, false) * (
							$stealPenalty / 100
						)
					);
					
					// Remove points from current user
					$this->getCurrencyRepo()
						->removeCurrencyAmount(
							$currency,
							'stealfail',
							$points,
							$visitor
						)
					;
				}
				
				if (!$currency->isIntegrated())
				{
					$extra = [
						'user' => $visitor->username,
						'link' => $this->app()->router('public')->buildLink('nopath:members', $visitor),
						'currency_title' => $currency->title,
						'depends_on_addon_id' => 'DBTech/Shop',
					];
					
					/** @var \XF\Repository\UserAlert $alertRepo */
					$alertRepo = $this->repository('XF:UserAlert');
					$alertRepo->alert(
						$user,
						$visitor->user_id,
						$visitor->username,
						'user',
						$visitor->user_id,
						"dbt_shop_steal_fail",
						$extra
					);
				}
				
				return $this->redirect(
					$this->buildLink('dbtech-shop/steal', null, ['failure' => true]),
					\XF::phrase('dbtech_shop_funds_were_not_stolen')
				);
			}
		}
		
		$viewParams = [
			'stealChance' => $stealChance,
			'stealAmount' => $stealAmount,
			'currencies' => $currencies->pluckNamed('title', 'currency_id'),
			'success' => $this->filter('success', 'bool'),
			'failure' => $this->filter('failure', 'bool')
		];
		return $this->view('DBTech\Shop:Steal\Index', 'dbtech_shop_steal', $viewParams);
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Currency|\XF\Mvc\Entity\Repository
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('DBTech\Shop:Currency');
	}

	/**
	 * @param array $activities
	 *
	 * @return bool|\XF\Phrase
	 */
	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('dbtech_shop_stealing_funds');
	}
}
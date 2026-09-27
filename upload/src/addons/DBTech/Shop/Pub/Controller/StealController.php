<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Phrase;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;
use XF\Repository\UserAlertRepository;

class StealController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		if (!\XF::app()->options()->dbtech_shop_steal_enabled)
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
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionIndex(): AbstractReply
	{
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/steal'));

		$currencies = \XF::app()->repository(CurrencyRepository::class)
			->findCurrenciesForList()
			->fetch()
			->filterViewable()
			->filter(function (Currency $currency): ?Currency
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

		$stealChance = \XF::app()->options()->dbtech_shop_steal_chance;
		$stealAmount = \XF::app()->options()->dbtech_shop_steal_amount;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)
			->filterActivePurchasesForUser(\XF::visitor())
		;
		foreach ($purchases AS $purchase)
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
						'currency_id' => $currencyId,
					])
				);
			}

			/** @var Currency $currency */
			$currency = $currencies->offsetGet($currencyId);

			if (\XF::app()->options()->dbtechShopMininumStealWallet
				&& $currency->getValueFromUser($visitor, false) <= \XF::app()->options()->dbtechShopMininumStealWallet
			)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_need_x_y_to_steal', [
					'amount' => $currency->getFormattedValue(\XF::app()->options()->dbtechShopMininumStealWallet),
					'currency' => $currency->title,
				]));
			}

			$user = \XF::app()->em()->findOne(User::class, ['username' => $userName]);
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

			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
			$purchases = \XF::app()->repository(PurchaseRepository::class)
				->filterActivePurchasesForUser($user)
			;
			foreach ($purchases AS $purchase)
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
				\XF::app()->repository(CurrencyRepository::class)
					->removeCurrencyAmount(
						$currency,
						'stealsuccess',
						$points,
						$user
					)
				;

				// Add points to current user
				\XF::app()->repository(CurrencyRepository::class)
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
						'link' => \XF::app()->router('public')->buildLink('nopath:members', $visitor),
						'currency' => $currency->title,
						'amount_stolen' => $currency->prefix . $currency->getFormattedValue($points) . $currency->suffix,
						'depends_on_addon_id' => 'DBTech/Shop',
					];

					$alertRepo = \XF::app()->repository(UserAlertRepository::class);
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
				$stealPenalty = \XF::app()->options()->dbtech_shop_steal_lose;

				/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
				$purchases = \XF::app()->repository(PurchaseRepository::class)
					->filterActivePurchasesForUser($user)
				;
				foreach ($purchases AS $purchase)
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
					\XF::app()->repository(CurrencyRepository::class)
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
						'link' => \XF::app()->router('public')->buildLink('nopath:members', $visitor),
						'currency_title' => $currency->title,
						'depends_on_addon_id' => 'DBTech/Shop',
					];

					$alertRepo = \XF::app()->repository(UserAlertRepository::class);
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
			'failure' => $this->filter('failure', 'bool'),
		];
		return $this->view(
			View\StealView::class,
			'dbtech_shop_steal',
			$viewParams
		);
	}

	/**
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_stealing_funds');
	}
}
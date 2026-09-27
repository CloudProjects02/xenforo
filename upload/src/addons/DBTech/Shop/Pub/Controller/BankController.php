<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Bank;
use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Phrase;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class BankController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		if (!\XF::app()->options()->dbtech_shop_bank_enabled)
		{
			throw $this->exception($this->notFound());
		}

		$this->assertRegistrationRequired();

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		if (!$visitor->canUseDbtechShopBank($error))
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
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/bank'));

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Bank> $bankedAmounts */
		$bankedAmounts = $currencyRepo->findBankedCurrenciesForUser()
			->fetch()
			->filter(function (Bank $bankedAmount): ?Bank
			{
				if (!$bankedAmount->Currency->canBank())
				{
					return null;
				}

				return $bankedAmount;
			})
		;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency> $currencies */
		$currencies = $currencyRepo->getBankableCurrencyList();
		foreach ($currencies AS $currency)
		{
			if (!$bankedAmounts->offsetExists($currency->currency_id))
			{
				$bank = \XF::app()->em()->create(Bank::class);
				$bank->user_id = \XF::visitor()->user_id;
				$bank->currency_id = $currency->currency_id;
				$bank->points = 0.00;
				$bank->save();

				$bank->hydrateRelation('Currency', $currency);

				$bankedAmounts->offsetSet($currency->currency_id, $bank);
			}
		}

		$viewParams = [
			'bankedAmounts' => $bankedAmounts,
		];
		return $this->view(
			View\Bank\IndexView::class,
			'dbtech_shop_bank',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionDeposit(ParameterBag $params): AbstractReply
	{
		$currency = $this->assertBankableCurrencyExists($params->currency_id);

		$bank = \XF::app()->em()->find(Bank::class, [
			'user_id' => \XF::visitor()->user_id,
			'currency_id' => $currency->currency_id,
		]);
		if (!$bank)
		{
			$bank = \XF::app()->em()->create(Bank::class);
			$bank->user_id = \XF::visitor()->user_id;
			$bank->currency_id = $currency->currency_id;
			$bank->points = 0.00;
			$bank->save();

			$bank->hydrateRelation('Currency', $currency);
		}

		if ($this->isPost())
		{
			$points = $this->filter('points', 'unum');
			if (!$points)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_nothing_to_do'));
			}

			if ($currency->getValueFromUser(null, false) < $points)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_not_enough_to_deposit_max_x', [
					'currency' => $currency->prefix . $currency->getValueFromUser() . $currency->suffix . ' ' . $currency->title,
				]));
			}

			$bank->points += $points;
			if ($currency->interest > 0)
			{
				// Only reset interest date if we gain positive interest to avoid gaming the system
				$bank->last_interest_date = \XF::$time;
			}

			if (!$bank->preSave())
			{
				return $this->error($bank->getErrors());
			}

			$bank->save();

			\XF::app()->repository(CurrencyRepository::class)
				->removeCurrencyAmount(
					$currency,
					'deposit',
					$points,
					\XF::visitor()
				)
			;

			return $this->redirect(
				$this->buildLink('dbtech-shop/bank'),
				\XF::phrase('dbtech_shop_funds_deposited_successfully')
			);
		}

		$viewParams = [
			'currency' => $currency,
			'bank' => $bank,
		];
		return $this->view(
			View\Bank\DepositView::class,
			'dbtech_shop_bank_deposit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionWithdraw(ParameterBag $params): AbstractReply
	{
		$currency = $this->assertBankableCurrencyExists($params->currency_id);

		$bank = \XF::app()->em()->find(Bank::class, [
			'user_id' => \XF::visitor()->user_id,
			'currency_id' => $currency->currency_id,
		]);
		if (!$bank)
		{
			$bank = \XF::app()->em()->create(Bank::class);
			$bank->user_id = \XF::visitor()->user_id;
			$bank->currency_id = $currency->currency_id;
			$bank->points = 0.00;
			$bank->save();

			$bank->hydrateRelation('Currency', $currency);
		}

		if ($this->isPost())
		{
			$points = $this->filter('points', 'unum');
			if (!$points)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_nothing_to_do'));
			}

			if ($bank->points < $points)
			{
				return $this->error(\XF::phraseDeferred('dbtech_shop_not_enough_to_withdraw_max_x', [
					'currency' => $currency->prefix . $currency->getFormattedValue($bank->points) . $currency->suffix . ' ' . $currency->title,
				]));
			}

			$bank->points -= $points;
			if ($currency->interest > 0)
			{
				// Only reset interest date if we gain positive interest to avoid gaming the system
				$bank->last_interest_date = \XF::$time;
			}

			if (!$bank->preSave())
			{
				return $this->error($bank->getErrors());
			}

			$bank->save();

			\XF::app()->repository(CurrencyRepository::class)
				->addCurrencyAmount(
					$currency,
					'withdraw',
					$points,
					\XF::visitor()
				)
			;

			return $this->redirect(
				$this->buildLink('dbtech-shop/bank'),
				\XF::phrase('dbtech_shop_funds_withdrawn_successfully')
			);
		}

		$viewParams = [
			'currency' => $currency,
			'bank' => $bank,
		];
		return $this->view(
			View\Bank\WithdrawView::class,
			'dbtech_shop_bank_withdraw',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionCollectInterest(ParameterBag $params): AbstractReply
	{
		$currency = $this->assertBankableCurrencyExists($params->currency_id);

		$bank = \XF::app()->em()->find(Bank::class, [
			'user_id' => \XF::visitor()->user_id,
			'currency_id' => $currency->currency_id,
		]);
		if (!$bank)
		{
			$bank = \XF::app()->em()->create(Bank::class);
			$bank->user_id = \XF::visitor()->user_id;
			$bank->currency_id = $currency->currency_id;
			$bank->points = 0.00;
			$bank->save();

			$bank->hydrateRelation('Currency', $currency);
		}

		if (!$bank->canManuallyCollectInterest())
		{
			return $this->error(\XF::phraseDeferred('dbtech_shop_cannot_manually_collect_interest'));
		}

		if ($bank->collectInterest())
		{
			$bank->save();

			\XF::app()->repository(CurrencyRepository::class)
				->logTransaction(
					$currency,
					'interest',
					$bank->points - $bank->getPreviousValue('points'),
					\XF::visitor()
				);
		}

		return $this->redirect($this->buildLink('dbtech-shop/bank'));
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Currency
	 * @throws Exception
	 */
	protected function assertBankableCurrencyExists(?int $id, array $with = [], ?string $phraseKey = null): Currency
	{
		/** @var Currency $currency */
		$currency = $this->assertRecordExists(Currency::class, $id, $with, $phraseKey);

		if (!$currency->canBank())
		{
			if (!$phraseKey)
			{
				$phraseKey = 'requested_page_not_found';
			}

			throw $this->exception(
				$this->notFound(\XF::phrase($phraseKey))
			);
		}

		return $currency;
	}

	/**
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_bank');
	}
}
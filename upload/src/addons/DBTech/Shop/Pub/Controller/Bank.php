<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Bank
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Bank extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!$this->options()->dbtech_shop_bank_enabled)
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
		
		if (!$visitor->canUseDbtechShopBank($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}
	
	
	/**
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/bank'));
		
		$currencyRepo = $this->getCurrencyRepo();
		
		/** @var \DBTech\Shop\Entity\Bank[]|ArrayCollection $bankedAmounts */
		$bankedAmounts = $currencyRepo->findBankedCurrenciesForUser()
			->fetch()
			->filter(function (\DBTech\Shop\Entity\Bank $bankedAmount): ?\DBTech\Shop\Entity\Bank
			{
				if (!$bankedAmount->Currency->canBank())
				{
					return null;
				}
				
				return $bankedAmount;
			})
		;
		
		/** @var \DBTech\Shop\Entity\Currency[]|ArrayCollection $currencies */
		$currencies = $currencyRepo->getBankableCurrencyList();
		foreach ($currencies as $currency)
		{
			if (!$bankedAmounts->offsetExists($currency->currency_id))
			{
				/** @var \DBTech\Shop\Entity\Bank $bank */
				$bank = $this->em()->create('DBTech\Shop:Bank');
				$bank->user_id = \XF::visitor()->user_id;
				$bank->currency_id = $currency->currency_id;
				$bank->points = 0.00;
				$bank->save();
				
				$bank->hydrateRelation('Currency', $currency);
				
				$bankedAmounts->offsetSet($currency->currency_id, $bank);
			}
		}
		
		$viewParams = [
			'bankedAmounts' => $bankedAmounts
		];
		return $this->view('DBTech\Shop:Bank\Index', 'dbtech_shop_bank', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionDeposit(ParameterBag $params)
	{
		$currency = $this->assertBankableCurrencyExists($params->currency_id);
		
		$bank = $this->em()->find('DBTech\Shop:Bank', [
			'user_id' => \XF::visitor()->user_id,
			'currency_id' => $currency->currency_id
		]);
		if (!$bank)
		{
			/** @var \DBTech\Shop\Entity\Bank $bank */
			$bank = $this->em()->create('DBTech\Shop:Bank');
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
					'currency' => $currency->prefix . $currency->getValueFromUser() . $currency->suffix . ' ' . $currency->title
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
			
			$this->getCurrencyRepo()
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
			'bank' => $bank
		];
		return $this->view('DBTech\Shop:Bank\Deposit', 'dbtech_shop_bank_deposit', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionWithdraw(ParameterBag $params)
	{
		$currency = $this->assertBankableCurrencyExists($params->currency_id);
		
		$bank = $this->em()->find('DBTech\Shop:Bank', [
			'user_id' => \XF::visitor()->user_id,
			'currency_id' => $currency->currency_id
		]);
		if (!$bank)
		{
			/** @var \DBTech\Shop\Entity\Bank $bank */
			$bank = $this->em()->create('DBTech\Shop:Bank');
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
					'currency' => $currency->prefix . $currency->getFormattedValue($bank->points) . $currency->suffix . ' ' . $currency->title
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
			
			$this->getCurrencyRepo()
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
			'bank' => $bank
		];
		return $this->view('DBTech\Shop:Bank\Withdraw', 'dbtech_shop_bank_withdraw', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionCollectInterest(ParameterBag $params)
	{
		$currency = $this->assertBankableCurrencyExists($params->currency_id);
		
		$bank = $this->em()->find('DBTech\Shop:Bank', [
			'user_id' => \XF::visitor()->user_id,
			'currency_id' => $currency->currency_id
		]);
		if (!$bank)
		{
			/** @var \DBTech\Shop\Entity\Bank $bank */
			$bank = $this->em()->create('DBTech\Shop:Bank');
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
			
			$this->getCurrencyRepo()->logTransaction(
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
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Currency|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertBankableCurrencyExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		/** @var \DBTech\Shop\Entity\Currency $currency */
		$currency = $this->assertRecordExists('DBTech\Shop:Currency', $id, $with, $phraseKey);
		
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
		return \XF::phrase('dbtech_shop_viewing_bank');
	}
}
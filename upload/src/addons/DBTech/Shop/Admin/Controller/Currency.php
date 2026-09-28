<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Class Currency
 * @package DBTech\Shop\Admin\Controller
 */
class Currency extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		$currencies = $this->getCurrencyRepo()->findCurrenciesForList()->fetch();

		$viewParams = [
			'currencies' => $currencies,
		];
		return $this->view('DBTech\Shop:Currency\Listing', 'dbtech_shop_currency_list', $viewParams);
	}

	/**
	 * @param \DBTech\Shop\Entity\Currency $currency
	 * @return \XF\Mvc\Reply\View
	 */
	protected function currencyAddEdit(\DBTech\Shop\Entity\Currency $currency): \XF\Mvc\Reply\AbstractReply
	{
		$creditsCurrencies = [];
		
		$addOns = \XF::app()->container('addon.cache');
		if (array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			$creditsCurrencies = $this->repository('DBTech\Credits:Currency')->getCurrencyTitlePairs(true);
		}
		
		$viewParams = [
			'currency' => $currency,
			'creditsCurrencies' => $creditsCurrencies,
		];
		return $this->view('DBTech\Shop:Currency\Edit', 'dbtech_shop_currency_edit', $viewParams);
	}

	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);
		return $this->currencyAddEdit($currency);
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionAdd(): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Currency $currency */
		$currency = $this->em()->create('DBTech\Shop:Currency');
		
		return $this->currencyAddEdit($currency);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Currency $currency
	 *
	 * @return FormAction
	 */
	protected function currencySaveProcess(\DBTech\Shop\Entity\Currency $currency): FormAction
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'title' => 'str',
			'display_order' => 'uint',
			'active' => 'bool',
			
			'column' => 'str',
			'decimals' => 'uint',
//			'negative' => 'uint',
			'privacy' => 'uint',
			'prefix' => 'str',
			'suffix' => 'str',
			'is_display_currency' => 'bool',
			'sidebar' => 'bool',
			'postbit' => 'bool',
			
			'credits_currency_id' => 'uint',
			
			'can_bank' => 'bool',
			'can_steal' => 'bool',
			'can_trade' => 'bool',
			'customshops' => 'bool',
			
			'per_thread' => 'unum',
			'per_reply' => 'unum',
			
			'interest' => 'num',
//			'steal_protect' => 'unum'
		]);
		
		$input['description'] = $this->plugin('XF:Editor')->fromInput('description');
		
		$form->basicEntitySave($currency, $input);
		
		return $form;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$this->assertPostOnly();
		
		if ($params->currency_id)
		{
			/** @var \DBTech\Shop\Entity\Currency $currency */
			$currency = $this->assertCurrencyExists($params->currency_id);
		}
		else
		{
			/** @var \DBTech\Shop\Entity\Currency $currency */
			$currency = $this->em()->create('DBTech\Shop:Currency');
		}
		
		$this->currencySaveProcess($currency)->run();

		return $this->redirect($this->buildLink('dbtech-shop/currencies') . $this->buildLinkHash($currency->currency_id));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionDelete(ParameterBag $params)
	{
		$currency = $this->assertCurrencyExists($params->currency_id);
		
		/** @var \XF\ControllerPlugin\Delete $plugin */
		$plugin = $this->plugin('XF:Delete');
		return $plugin->actionDelete(
			$currency,
			$this->buildLink('dbtech-shop/currencies/delete', $currency),
			$this->buildLink('dbtech-shop/currencies/edit', $currency),
			$this->buildLink('dbtech-shop/currencies'),
			$currency->currency_id
		);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Message
	 */
	public function actionToggle(): \XF\Mvc\Reply\Message
	{
		/** @var \XF\ControllerPlugin\Toggle $plugin */
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('DBTech\Shop:Currency', 'active');
	}

	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Currency|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertCurrencyExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:Currency', $id, $with, $phraseKey);
	}

	/**
	 * @return \DBTech\Shop\Repository\Currency|\XF\Mvc\Entity\Repository
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('DBTech\Shop:Currency');
	}
}
<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Shop\Admin\View;
use DBTech\Shop\Entity\Currency;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;

class CurrencyController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		$currencies = \XF::app()->repository(\DBTech\Shop\Repository\CurrencyRepository::class)
			->findCurrenciesForList()
			->fetch()
		;

		$viewParams = [
			'currencies' => $currencies,
		];
		return $this->view(
			View\Currency\ListingView::class,
			'dbtech_shop_currency_list',
			$viewParams
		);
	}

	/**
	 * @param Currency $currency
	 * @return AbstractReply
	 */
	protected function currencyAddEdit(Currency $currency): AbstractReply
	{
		$creditsCurrencies = [];

		$addOns = \XF::app()->container('addon.cache');
		if (array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			$creditsCurrencies = \XF::app()->repository(CurrencyRepository::class)->getCurrencyTitlePairs(true);
		}

		$viewParams = [
			'currency' => $currency,
			'creditsCurrencies' => $creditsCurrencies,
		];
		return $this->view(
			View\Currency\EditView::class,
			'dbtech_shop_currency_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		/** @var Currency $currency */
		$currency = $this->assertCurrencyExists($params->currency_id);
		return $this->currencyAddEdit($currency);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionAdd(): AbstractReply
	{
		$currency = \XF::app()->em()->create(Currency::class);

		return $this->currencyAddEdit($currency);
	}

	/**
	 * @param Currency $currency
	 *
	 * @return FormAction
	 */
	protected function currencySaveProcess(Currency $currency): FormAction
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

		$input['description'] = $this->plugin(EditorPlugin::class)->fromInput('description');

		$form->basicEntitySave($currency, $input);

		return $form;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->currency_id)
		{
			/** @var Currency $currency */
			$currency = $this->assertCurrencyExists($params->currency_id);
		}
		else
		{
			$currency = \XF::app()->em()->create(Currency::class);
		}

		$this->currencySaveProcess($currency)->run();

		return $this->redirect($this->buildLink('dbtech-shop/currencies') . $this->buildLinkHash($currency->currency_id));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$currency = $this->assertCurrencyExists($params->currency_id);

		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$currency,
			$this->buildLink('dbtech-shop/currencies/delete', $currency),
			$this->buildLink('dbtech-shop/currencies/edit', $currency),
			$this->buildLink('dbtech-shop/currencies'),
			$currency->currency_id
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionToggle(): AbstractReply
	{
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(Currency::class);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Currency
	 * @throws Exception
	 */
	protected function assertCurrencyExists(?int $id, array $with = [], ?string $phraseKey = null): Currency
	{
		return $this->assertRecordExists(Currency::class, $id, $with, $phraseKey);
	}
}
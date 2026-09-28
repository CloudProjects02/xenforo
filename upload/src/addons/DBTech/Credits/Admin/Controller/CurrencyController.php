<?php

namespace DBTech\Credits\Admin\Controller;

use DBTech\Credits\Admin\View;
use DBTech\Credits\Entity\Currency;
use DBTech\Credits\Repository\CurrencyRepository;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\InputFilterer;
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
		$this->assertAdminPermission('dbtechCredits');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		$currencies = \XF::app()->repository(CurrencyRepository::class)
			->findCurrenciesForList()
			->fetch()
		;

		$viewParams = [
			'currencies' => $currencies,
		];
		return $this->view(
			View\Currency\ListingView::class,
			'dbtech_credits_currency_list',
			$viewParams
		);
	}

	/**
	 * @param Currency $currency
	 * @return AbstractReply
	 */
	protected function currencyAddEdit(Currency $currency): AbstractReply
	{
		$viewParams = [
			'currency' => $currency,
		];
		return $this->view(
			View\Currency\EditView::class,
			'dbtech_credits_currency_edit',
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
			'title' => InputFilterer::STRING,
			'display_order' => InputFilterer::UNSIGNED,
			'active' => InputFilterer::BOOLEAN,

			'column' => InputFilterer::STRING,
			'decimals' => InputFilterer::UNSIGNED,
			'negative' => InputFilterer::UNSIGNED,
			'privacy' => InputFilterer::UNSIGNED,
			'prefix' => InputFilterer::STRING,
			'suffix' => InputFilterer::STRING,
			'is_display_currency' => InputFilterer::BOOLEAN,
			'show_amounts' => InputFilterer::BOOLEAN,
			'sidebar' => InputFilterer::BOOLEAN,
			'member_dropdown' => InputFilterer::BOOLEAN,
			'postbit' => InputFilterer::BOOLEAN,

			'earnmax' => InputFilterer::UNUM,
			'maxtime' => InputFilterer::UNSIGNED,
			'value' => InputFilterer::NUM,
			'inbound' => InputFilterer::BOOLEAN,
			'outbound' => InputFilterer::BOOLEAN,
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

		return $this->redirect($this->buildLink('dbtech-credits/currencies') . $this->buildLinkHash($currency->currency_id));
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
			$this->buildLink('dbtech-credits/currencies/delete', $currency),
			$this->buildLink('dbtech-credits/currencies/edit', $currency),
			$this->buildLink('dbtech-credits/currencies'),
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
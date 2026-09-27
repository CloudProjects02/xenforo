<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Admin\View;
use DBTech\Shop\Entity\Lottery;
use DBTech\Shop\Entity\LotteryPrize;
use DBTech\Shop\Repository\LotteryRepository;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;

class LotteryController extends AbstractController
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
	public function actionLottery(): AbstractReply
	{
		$lotteries = \XF::app()->repository(LotteryRepository::class)
			->findLotteriesForList()
			->fetch()
		;

		$viewParams = [
			'lotteries' => $lotteries,
		];
		return $this->view(
			View\Lottery\ListingView::class,
			'dbtech_shop_lottery_list',
			$viewParams
		);
	}

	/**
	 * @param Lottery $lottery
	 * @return AbstractReply
	 */
	protected function lotteryAddEdit(Lottery $lottery): AbstractReply
	{
		$viewParams = [
			'lottery' => $lottery,
			'nextCounter' => count($lottery->PrizeMap),
		];
		return $this->view(
			View\Lottery\EditView::class,
			'dbtech_shop_lottery_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionLotteryEdit(ParameterBag $params): AbstractReply
	{
		/** @var Lottery $lottery */
		$lottery = $this->assertLotteryExists($params->lottery_id);
		return $this->lotteryAddEdit($lottery);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionLotteryAdd(): AbstractReply
	{
		$lottery = \XF::app()->em()->create(Lottery::class);

		return $this->lotteryAddEdit($lottery);
	}

	/**
	 * @param Lottery $lottery
	 *
	 * @return FormAction
	 */
	protected function lotterySaveProcess(Lottery $lottery): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'active' => 'bool',

			'ticket_price' => 'unum',
			'currency_id' => 'uint',
			'numbers' => 'array-uint',
			'draw_interval_days' => 'uint',
			'next_draw_date' => 'datetime',
		]);

		$input['description'] = $this->plugin(EditorPlugin::class)->fromInput('description');

		$form->basicEntitySave($lottery, $input);

		$lotteryPrizes = $this->filter('lottery_prizes', 'array');
		$form->complete(function () use ($lottery, $lotteryPrizes)
		{
			$repo = \XF::app()->repository(LotteryRepository::class);
			$repo->updateContentAssociations($lottery->lottery_id, $lotteryPrizes);
		});

		return $form;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionLotterySave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->lottery_id)
		{
			/** @var Lottery $lottery */
			$lottery = $this->assertLotteryExists($params->lottery_id);
		}
		else
		{
			$lottery = \XF::app()->em()->create(Lottery::class);
		}

		$this->lotterySaveProcess($lottery)->run();

		return $this->redirect($this->buildLink('dbtech-shop/lotteries/lottery') . $this->buildLinkHash($lottery->lottery_id));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionLotteryDelete(ParameterBag $params): AbstractReply
	{
		$lottery = $this->assertLotteryExists($params->lottery_id);

		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$lottery,
			$this->buildLink('dbtech-shop/lotteries/lottery/delete', $lottery),
			$this->buildLink('dbtech-shop/lotteries/lottery/edit', $lottery),
			$this->buildLink('dbtech-shop/lotteries/lottery'),
			$lottery->title
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionLotteryToggle(): AbstractReply
	{
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(Lottery::class);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionLotteryPrize(): AbstractReply
	{
		$lotteryPrizes = \XF::app()->repository(LotteryRepository::class)
			->findLotteryPrizesForList()
			->fetch()
		;

		$viewParams = [
			'lotteryPrizes' => $lotteryPrizes,
		];
		return $this->view(
			View\LotteryPrize\ListingView::class,
			'dbtech_shop_lottery_prize_list',
			$viewParams
		);
	}

	/**
	 * @param LotteryPrize $lotteryPrize
	 * @return AbstractReply
	 */
	protected function lotteryPrizeAddEdit(LotteryPrize $lotteryPrize): AbstractReply
	{
		$viewParams = [
			'lotteryPrize' => $lotteryPrize,
		];
		return $this->view(
			View\LotteryPrize\EditView::class,
			'dbtech_shop_lottery_prize_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionLotteryPrizeEdit(ParameterBag $params): AbstractReply
	{
		/** @var LotteryPrize $lotteryPrize */
		$lotteryPrize = $this->assertLotteryPrizeExists($params->lottery_prize_id);
		return $this->lotteryPrizeAddEdit($lotteryPrize);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionLotteryPrizeAdd(): AbstractReply
	{
		$lotteryPrize = \XF::app()->em()->create(LotteryPrize::class);

		return $this->lotteryPrizeAddEdit($lotteryPrize);
	}

	/**
	 * @param LotteryPrize $lotteryPrize
	 *
	 * @return FormAction
	 */
	protected function lotteryPrizeSaveProcess(LotteryPrize $lotteryPrize): FormAction
	{
		$form = $this->formAction();

		$input = $this->filter([
			'title' => 'str',
			'active' => 'bool',

			'numbers' => 'array-uint',
		]);

		$input['description'] = $this->plugin(EditorPlugin::class)->fromInput('description');

		$form->basicEntitySave($lotteryPrize, $input);

		return $form;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionLotteryPrizeSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->lottery_prize_id)
		{
			/** @var LotteryPrize $lotteryPrize */
			$lotteryPrize = $this->assertLotteryPrizeExists($params->lottery_prize_id);
		}
		else
		{
			$lotteryPrize = \XF::app()->em()->create(LotteryPrize::class);
		}

		$this->lotteryPrizeSaveProcess($lotteryPrize)->run();

		return $this->redirect($this->buildLink('dbtech-shop/lotteries/prizes') . $this->buildLinkHash($lotteryPrize->lottery_prize_id));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionLotteryPrizeDelete(ParameterBag $params): AbstractReply
	{
		$lotteryPrize = $this->assertLotteryPrizeExists($params->lottery_prize_id);

		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$lotteryPrize,
			$this->buildLink('dbtech-shop/lotteries/prizes/delete', $lotteryPrize),
			$this->buildLink('dbtech-shop/lotteries/prizes/edit', $lotteryPrize),
			$this->buildLink('dbtech-shop/lotteries/prizes'),
			$lotteryPrize->title
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionLotteryPrizeToggle(): AbstractReply
	{
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(LotteryPrize::class);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Lottery
	 * @throws Exception
	 */
	protected function assertLotteryExists(?int $id, array $with = [], ?string $phraseKey = null): Lottery
	{
		return $this->assertRecordExists(Lottery::class, $id, $with, $phraseKey);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return LotteryPrize
	 * @throws Exception
	 */
	protected function assertLotteryPrizeExists(?int $id, array $with = [], ?string $phraseKey = null): LotteryPrize
	{
		return $this->assertRecordExists(LotteryPrize::class, $id, $with, $phraseKey);
	}
}
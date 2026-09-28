<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Class Lottery
 * @package DBTech\Shop\Admin\Controller
 */
class Lottery extends AbstractController
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
	public function actionLottery(): \XF\Mvc\Reply\AbstractReply
	{
		$lotteries = $this->getLotteryRepo()->findLotteriesForList()->fetch();

		$viewParams = [
			'lotteries' => $lotteries,
		];
		return $this->view('DBTech\Shop:Lottery\Listing', 'dbtech_shop_lottery_list', $viewParams);
	}

	/**
	 * @param \DBTech\Shop\Entity\Lottery $lottery
	 * @return \XF\Mvc\Reply\View
	 */
	protected function lotteryAddEdit(\DBTech\Shop\Entity\Lottery $lottery): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = [
			'lottery' => $lottery,
			'nextCounter' => count($lottery->PrizeMap),
		];
		return $this->view('DBTech\Shop:Lottery\Edit', 'dbtech_shop_lottery_edit', $viewParams);
	}

	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLotteryEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Lottery $lottery */
		$lottery = $this->assertLotteryExists($params->lottery_id);
		return $this->lotteryAddEdit($lottery);
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionLotteryAdd(): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Lottery $lottery */
		$lottery = $this->em()->create('DBTech\Shop:Lottery');
		
		return $this->lotteryAddEdit($lottery);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Lottery $lottery
	 *
	 * @return FormAction
	 */
	protected function lotterySaveProcess(\DBTech\Shop\Entity\Lottery $lottery): FormAction
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
		
		$input['description'] = $this->plugin('XF:Editor')->fromInput('description');
		
		$form->basicEntitySave($lottery, $input);
		
		$lotteryPrizes = $this->filter('lottery_prizes', 'array');
		$form->complete(function () use ($lottery, $lotteryPrizes)
		{
			/** @var \DBTech\Shop\Repository\Lottery $repo */
			$repo = $this->repository('DBTech\Shop:Lottery');
			$repo->updateContentAssociations($lottery->lottery_id, $lotteryPrizes);
		});
		
		return $form;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionLotterySave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$this->assertPostOnly();
		
		if ($params->lottery_id)
		{
			/** @var \DBTech\Shop\Entity\Lottery $lottery */
			$lottery = $this->assertLotteryExists($params->lottery_id);
		}
		else
		{
			/** @var \DBTech\Shop\Entity\Lottery $lottery */
			$lottery = $this->em()->create('DBTech\Shop:Lottery');
		}
		
		$this->lotterySaveProcess($lottery)->run();

		return $this->redirect($this->buildLink('dbtech-shop/lotteries/lottery') . $this->buildLinkHash($lottery->lottery_id));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLotteryDelete(ParameterBag $params)
	{
		$lottery = $this->assertLotteryExists($params->lottery_id);
		
		/** @var \XF\ControllerPlugin\Delete $plugin */
		$plugin = $this->plugin('XF:Delete');
		return $plugin->actionDelete(
			$lottery,
			$this->buildLink('dbtech-shop/lotteries/lottery/delete', $lottery),
			$this->buildLink('dbtech-shop/lotteries/lottery/edit', $lottery),
			$this->buildLink('dbtech-shop/lotteries/lottery'),
			$lottery->title
		);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Message
	 */
	public function actionLotteryToggle(): \XF\Mvc\Reply\Message
	{
		/** @var \XF\ControllerPlugin\Toggle $plugin */
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('DBTech\Shop:Lottery', 'active');
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionLotteryPrize(): \XF\Mvc\Reply\AbstractReply
	{
		$lotteryPrizes = $this->getLotteryRepo()->findLotteryPrizesForList()->fetch();
		
		$viewParams = [
			'lotteryPrizes' => $lotteryPrizes,
		];
		return $this->view('DBTech\Shop:LotteryPrize\Listing', 'dbtech_shop_lottery_prize_list', $viewParams);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\LotteryPrize $lotteryPrize
	 * @return \XF\Mvc\Reply\View
	 */
	protected function lotteryPrizeAddEdit(\DBTech\Shop\Entity\LotteryPrize $lotteryPrize): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = [
			'lotteryPrize' => $lotteryPrize,
		];
		return $this->view('DBTech\Shop:LotteryPrize\Edit', 'dbtech_shop_lottery_prize_edit', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLotteryPrizeEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\LotteryPrize $lotteryPrize */
		$lotteryPrize = $this->assertLotteryPrizeExists($params->lottery_prize_id);
		return $this->lotteryPrizeAddEdit($lotteryPrize);
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionLotteryPrizeAdd(): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\LotteryPrize $lotteryPrize */
		$lotteryPrize = $this->em()->create('DBTech\Shop:LotteryPrize');
		
		return $this->lotteryPrizeAddEdit($lotteryPrize);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\LotteryPrize $lotteryPrize
	 *
	 * @return FormAction
	 */
	protected function lotteryPrizeSaveProcess(\DBTech\Shop\Entity\LotteryPrize $lotteryPrize): FormAction
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'title' => 'str',
			'active' => 'bool',
			
			'numbers' => 'array-uint',
		]);
		
		$input['description'] = $this->plugin('XF:Editor')->fromInput('description');
		
		$form->basicEntitySave($lotteryPrize, $input);
		
		return $form;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionLotteryPrizeSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$this->assertPostOnly();
		
		if ($params->lottery_prize_id)
		{
			/** @var \DBTech\Shop\Entity\LotteryPrize $lotteryPrize */
			$lotteryPrize = $this->assertLotteryPrizeExists($params->lottery_prize_id);
		}
		else
		{
			/** @var \DBTech\Shop\Entity\LotteryPrize $lotteryPrize */
			$lotteryPrize = $this->em()->create('DBTech\Shop:LotteryPrize');
		}
		
		$this->lotteryPrizeSaveProcess($lotteryPrize)->run();
		
		return $this->redirect($this->buildLink('dbtech-shop/lotteries/prizes') . $this->buildLinkHash($lotteryPrize->lottery_prize_id));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLotteryPrizeDelete(ParameterBag $params)
	{
		$lotteryPrize = $this->assertLotteryPrizeExists($params->lottery_prize_id);
		
		/** @var \XF\ControllerPlugin\Delete $plugin */
		$plugin = $this->plugin('XF:Delete');
		return $plugin->actionDelete(
			$lotteryPrize,
			$this->buildLink('dbtech-shop/lotteries/prizes/delete', $lotteryPrize),
			$this->buildLink('dbtech-shop/lotteries/prizes/edit', $lotteryPrize),
			$this->buildLink('dbtech-shop/lotteries/prizes'),
			$lotteryPrize->title
		);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Message
	 */
	public function actionLotteryPrizeToggle(): \XF\Mvc\Reply\Message
	{
		/** @var \XF\ControllerPlugin\Toggle $plugin */
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('DBTech\Shop:LotteryPrize', 'active');
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Lottery|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertLotteryExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:Lottery', $id, $with, $phraseKey);
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\LotteryPrize|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertLotteryPrizeExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:LotteryPrize', $id, $with, $phraseKey);
	}

	/**
	 * @return \DBTech\Shop\Repository\Lottery|\XF\Mvc\Entity\Repository
	 */
	protected function getLotteryRepo()
	{
		return $this->repository('DBTech\Shop:Lottery');
	}
}
<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Lottery;
use DBTech\Shop\Entity\LotteryHistory;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\LotteryRepository;
use DBTech\Shop\Service\Lottery\BuyTicketService;
use DBTech\Shop\XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Pub\Controller\AbstractController;

class LotteryController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		if (!\XF::app()->options()->dbtech_shop_lottery_enabled)
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

		if (!$visitor->canViewDbtechShopLotteries($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		if ($params->lottery_id)
		{
			return $this->rerouteController(LotteryController::class, 'Lottery', $params);
		}

		$finder = \XF::app()->repository(LotteryRepository::class)
			->findLotteriesForList()
			->with('Currency')
		;
		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopLotteriesPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/lotteries');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/lotteries', null, ['page' => $page]));

		$lotteries = $finder->limitByPage($page, $perPage)->fetch();
		$lotteries = $lotteries->filterViewable();

		$viewParams = [
			'lotteries' => $lotteries,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
		];
		return $this->view(
			View\Lottery\ListView::class,
			'dbtech_shop_lottery_list',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionLottery(ParameterBag $params): AbstractReply
	{
		$lottery = $this->assertLotteryExists($params->lottery_id);

		if (!$lottery->canView())
		{
			throw $this->exception($this->noPermission());
		}

		$finder = $lottery->getRelationFinder('History')
			->order('draw_date', 'DESC')
		;
		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopLotteriesPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/lotteries', $lottery);
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/lotteries', $lottery, ['page' => $page]));

		$history = $finder->limitByPage($page, $perPage)->fetch();

		$ownTickets = 0;
		if ($lottery->next_draw_date >= \XF::$time)
		{
			$ownTickets = $lottery->getRelationFinder('CurrentTickets')
				->where('user_id', \XF::visitor()->user_id)
				->total()
			;
		}

		$viewParams = [
			'lottery' => $lottery,
			'historyEntries' => $history,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'ownTickets' => $ownTickets,
		];
		return $this->view(
			View\Lottery\ViewView::class,
			'dbtech_shop_lottery_view',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionViewDraw(ParameterBag $params): AbstractReply
	{
		$entry = $this->assertLotteryHistoryExists($params->lottery_history_id, ['Lottery']);
		$lottery = $entry->Lottery;

		if (!$lottery->canView())
		{
			throw $this->exception($this->noPermission());
		}

		$viewParams = [
			'lottery' => $lottery,
			'entry' => $entry,
		];
		return $this->view(
			View\Lottery\ViewDrawView::class,
			'dbtech_shop_lottery_view_draw',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionViewTickets(ParameterBag $params): AbstractReply
	{
		$lottery = $this->assertLotteryExists($params->lottery_id);

		if (!$lottery->canView())
		{
			throw $this->exception($this->noPermission());
		}

		$tickets = $lottery->getRelationFinder('CurrentTickets')
			->where('user_id', \XF::visitor()->user_id)
			->fetch()
		;
		if (!$tickets->count())
		{
			return $this->error(\XF::phrase('dbtech_shop_you_havent_purchased_any_tickets'));
		}

		$viewParams = [
			'lottery' => $lottery,
			'tickets' => $tickets,
		];
		return $this->view(
			View\Lottery\ViewTicketsView::class,
			'dbtech_shop_lottery_view_tickets',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionBuyTicket(ParameterBag $params): AbstractReply
	{
		$lottery = $this->assertLotteryExists($params->lottery_id, ['Currency']);

		if (!$lottery->canView())
		{
			throw $this->exception($this->noPermission());
		}

		if (!$lottery->canBuyTicket())
		{
			return $this->error(\XF::phraseDeferred('dbtech_shop_cannot_buy_ticket_for_this_lottery'));
		}

		if ($this->isPost())
		{
			$ticketService = \XF::app()->service(BuyTicketService::class, $lottery);
			$ticketService->setNumbers($this->filter('numbers', 'array-uint'));

			if (!$ticketService->validate($errors))
			{
				return $this->error($errors);
			}

			$ticketService->save();

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('dbtech-shop/lotteries', $lottery)),
				\XF::phrase('dbtech_shop_lottery_ticket_purchased_good_luck')
			);
		}

		$numbers = [];
		for ($i = 1; $i <= $lottery->numbers['total']; $i++)
		{
			$numbers[$i] = $i;
		}

		$viewParams = [
			'lottery' => $lottery,
			'numbers' => $numbers,
		];
		return $this->view(
			View\Lottery\BuyTicketView::class,
			'dbtech_shop_lottery_buy_ticket',
			$viewParams
		);
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
	 * @return LotteryHistory
	 * @throws Exception
	 */
	protected function assertLotteryHistoryExists(?int $id, array $with = [], ?string $phraseKey = null): LotteryHistory
	{
		return $this->assertRecordExists(LotteryHistory::class, $id, $with, $phraseKey);
	}
}
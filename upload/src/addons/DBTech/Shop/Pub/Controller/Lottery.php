<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Lottery
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Lottery extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!$this->options()->dbtech_shop_lottery_enabled)
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
		
		if (!$visitor->canViewDbtechShopLotteries($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params)
	{
		if ($params->lottery_id)
		{
			return $this->rerouteController('DBTech\Shop:Lottery', 'Lottery', $params);
		}
		
		$finder = $this->getLotteryRepo()
			->findLotteriesForList()
			->with('Currency')
		;
		$total = $finder->total();
		
		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopLotteriesPerPage;
		
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
		return $this->view('DBTech\Shop:Lottery\List', 'dbtech_shop_lottery_list', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLottery(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
		$perPage = $this->options()->dbtechShopLotteriesPerPage;
		
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
			'ownTickets' => $ownTickets
		];
		return $this->view('DBTech\Shop:Lottery\View', 'dbtech_shop_lottery_view', $viewParams);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionViewDraw(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$entry = $this->assertLotteryHistoryExists($params->lottery_history_id, ['Lottery']);
		$lottery = $entry->Lottery;

		if (!$lottery->canView())
		{
			throw $this->exception($this->noPermission());
		}

		$viewParams = [
			'lottery' => $lottery,
			'entry' => $entry
		];
		return $this->view('DBTech\Shop:Lottery\ViewDraw', 'dbtech_shop_lottery_view_draw', $viewParams);
	}

	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionViewTickets(ParameterBag $params)
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
			'tickets' => $tickets
		];
		return $this->view('DBTech\Shop:Lottery\ViewTickets', 'dbtech_shop_lottery_view_tickets', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Message|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionBuyTicket(ParameterBag $params)
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
			/** @var \DBTech\Shop\Service\Lottery\BuyTicket $ticketService */
			$ticketService = $this->service('DBTech\Shop:Lottery\BuyTicket', $lottery);
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
			'numbers' => $numbers
		];
		return $this->view('DBTech\Shop:Lottery\BuyTicket', 'dbtech_shop_lottery_buy_ticket', $viewParams);
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Lottery
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertLotteryExists(?int $id, $with = null, ?string $phraseKey = null): \DBTech\Shop\Entity\Lottery
	{
		return $this->assertRecordExists('DBTech\Shop:Lottery', $id, $with, $phraseKey);
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\LotteryHistory
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertLotteryHistoryExists(?int $id, $with = null, ?string $phraseKey = null): \DBTech\Shop\Entity\LotteryHistory
	{
		return $this->assertRecordExists('DBTech\Shop:LotteryHistory', $id, $with, $phraseKey);
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Lottery|\XF\Mvc\Entity\Repository
	 */
	protected function getLotteryRepo()
	{
		return $this->repository('DBTech\Shop:Lottery');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Currency|\XF\Mvc\Entity\Repository
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('DBTech\Shop:Currency');
	}
}
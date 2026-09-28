<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Trade
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Trade extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!$this->options()->dbtech_shop_trade_enabled)
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
		
		if (!$visitor->canUseDbtechShopTrade($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Reroute
	 */
	public function actionIndex(ParameterBag $params): \XF\Mvc\Reply\Reroute
	{
		if ($params->trade_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}
		
		return $this->rerouteController(__CLASS__, 'pending');
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionPending(): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Trade[]|\XF\Mvc\Entity\ArrayCollection $trades */
		$trades = $this->getTradeRepo()
			->findParticipatingPendingTrades()
			->fetch()
		;
		
		$viewParams = [
			'selectedTab' => 'pending',
			'trades' => $trades,
			'phrase' => \XF::phrase('dbtech_shop_no_pending_trades_frontend')
		];
		
		if ($this->filter('_xfWithData', 'bool'))
		{
			// From AJAX
			$viewParams['_noWrap'] = true;
		}
		
		return $this->view('DBTech\Shop:Trade\List', 'dbtech_shop_trade_list', $viewParams);
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCompleted(): \XF\Mvc\Reply\AbstractReply
	{
		$finder = $this->getTradeRepo()
			->findParticipatingCompletedTrades()
		;
		
		$total = $finder->total();
		
		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopTradesPerPage;
		
		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/trades/completed');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/trades/completed', null, ['page' => $page]));
		
		/** @var \DBTech\Shop\Entity\Trade[]|\XF\Mvc\Entity\ArrayCollection $trades */
		$trades = $finder->limitByPage($page, $perPage)->fetch();
		$trades = $trades->filterViewable();
		
		$viewParams = [
			'selectedTab' => 'completed',
			'trades' => $trades,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'phrase' => \XF::phrase('dbtech_shop_no_past_trades_frontend')
		];
		
		if ($this->filter('_xfWithData', 'bool'))
		{
			// From AJAX
			$viewParams['_noWrap'] = true;
		}
		
		return $this->view('DBTech\Shop:Trade\List', 'dbtech_shop_trade_list', $viewParams);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAdd()
	{
		if ($this->isPost())
		{
			$creator = $this->setupTradeCreate();
			
			if (!$creator->validate($errors))
			{
				return $this->error($errors);
			}
			
			$creator->save();
			
			return $this->redirect(
				$this->buildLink('dbtech-shop/trades'),
				\XF::phrase('dbtech_shop_trade_requested')
			);
		}
		
		return $this->view('DBTech\Shop:Trade\Add', 'dbtech_shop_trade_add');
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAcceptInvite(ParameterBag $params)
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canAcceptInvite($error))
		{
			return $this->error($error ?: \XF::phraseDeferred('dbtech_shop_trade_invite_cannot_be_accepted'));
		}
		
		if ($this->isPost())
		{
			$inviter = $this->setupTradeInviteAccept($trade);
			
			if (!$inviter->validate($errors))
			{
				return $this->error($errors);
			}
			
			$inviter->save();
			
			return $this->redirect(
				$this->buildLink('dbtech-shop/trades', $trade),
				\XF::phrase('dbtech_shop_trade_invite_accepted')
			);
		}
		
		$viewParams = [
			'trade' => $trade
		];
		return $this->view('DBTech\Shop:Trade\AcceptInvite', 'dbtech_shop_trade_accept_invite', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionView(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canView())
		{
			throw $this->exception($this->noPermission());
		}
		
		$offers = [];
		
		/** @var \DBTech\Shop\Entity\TradeOffer[]|\XF\Mvc\Entity\ArrayCollection $offersByContentType */
		$offersByContentType = $trade->Offers;
		$offersByContentType = $offersByContentType->groupBy('content_type');
		
		foreach ($this->getTradeRepo()->getTradeOfferHandlers() as $contentType => $handler)
		{
			$offers[$contentType] = [];
			
			if (empty($offersByContentType[$contentType]))
			{
				continue;
			}
			
			/** @var \DBTech\Shop\Entity\TradeOffer $offer */
			foreach ($offersByContentType[$contentType] as $offer)
			{
				if (!isset($offers[$contentType][$offer->user_id]))
				{
					$offers[$contentType][$offer->user_id] = [];
				}
				
				$offers[$contentType][$offer->user_id][] = $offer;
			}
		}
		
		$page = $this->filterPage();
		$perPage = $this->options()->messagesPerPage;
		
		/** @var \XF\Repository\UserAlert $userAlertRepo */
		$userAlertRepo = $this->repository('XF:UserAlert');
		
		if ($trade->canViewPostsInTrade())
		{
			$tradeRepo = $this->getTradePostRepo();
			$tradePostFinder = $tradeRepo->findTradePostsInTrade($trade, [
				'allowOwnPending' => $this->hasContentPendingApproval()
			]);
			$tradePosts = $tradePostFinder->limitByPage($page, $perPage)->fetch();
			
			$total = $tradePostFinder->total();
			
			$isRobot = $this->isRobot();
			$tradePosts = $tradeRepo->addCommentsToTradePosts($tradePosts, $isRobot);
			
			/** @var \XF\Repository\Unfurl $unfurlRepo */
			$unfurlRepo = $this->repository('XF:Unfurl');
			$unfurlRepo->addUnfurlsToContent($tradePosts, $isRobot);
			
			$commentIds = [];
			foreach ($tradePosts AS $tradePost)
			{
				if ($tradePost->LatestComments)
				{
					$commentIds = array_merge($commentIds, $tradePost->LatestComments->keys());
				}
			}
			
			$userAlertRepo->markUserAlertsReadForContent('dbtech_shop_trade_post', $tradePosts->keys());
			$userAlertRepo->markUserAlertsReadForContent('dbtech_shop_trade_comment', $commentIds);
		}
		else
		{
			$total = 0;
			$tradePosts = $this->em()->getEmptyCollection();
		}
		
		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/trades', $trade);
		
		$canInlineMod = false;
		foreach ($tradePosts AS $tradePost)
		{
			if ($tradePost->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}
		
		$viewParams = [
			'trade' => $trade,
			'offers' => $offers,
			
			'tradePosts' => $tradePosts,
			'canInlineMod' => $canInlineMod,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total
		];
		return $this->view('DBTech\Shop:Trade\View', 'dbtech_shop_trade_view', $viewParams);
	}
	
	
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionViewOffer(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canView())
		{
			throw $this->exception($this->noPermission());
		}
		
		$contentType = $this->filter('content_type', 'str');
		$contentId = $this->filter('content_id', 'uint');
		$userId = $this->filter('user_id', 'uint');
		
		$handler = $this->getTradeRepo()->getTradeOfferHandler($contentType);
		if (!$handler)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_page_not_found')));
		}
		
		/** @var \DBTech\Shop\Entity\TradeOffer $tradeOffer */
		$tradeOffer = $trade->getRelationFinder('Offers')
			->where('content_type', $contentType)
			->where('content_id', $contentId)
			->where('user_id', $userId)
			->fetchOne()
		;
		
		$viewParams = [
			'trade' => $trade,
			'tradeOffer' => $tradeOffer,
			'handler' => $handler
		];
		return $this->view('DBTech\Shop:Trade\ViewOffer', 'dbtech_shop_trade_view_offer', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionModify(ParameterBag $params)
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canView())
		{
			throw $this->exception($this->noPermission());
		}
		
		if (!$trade->canEdit())
		{
			return $this->error(\XF::phraseDeferred('dbtech_shop_trade_cannot_be_edited'));
		}
		
		/** @var \DBTech\Shop\Repository\Trade $tradeRepo */
		$tradeRepo = $this->getTradeRepo();
		
		$offers = $tradeRepo->getGroupedOffersFromTrade($trade);
		$handlers = $tradeRepo->getTradeOfferHandlers();
		
		$viewParams = [
			'trade' => $trade,
			'offers' => $offers,
			'handlers' => $handlers
		];
		return $this->view('DBTech\Shop:Trade\Modify', 'dbtech_shop_trade_edit', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionSave(ParameterBag $params)
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canEdit())
		{
			return $this->error(\XF::phraseDeferred('dbtech_shop_trade_cannot_be_edited'));
		}
		
		$editor = $this->setupTradeEdit($trade);
		
		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}
		
		$editor->save();
		
		return $this->redirect(
			$this->buildLink('dbtech-shop/trades', $trade),
			\XF::phrase('dbtech_shop_trade_modified')
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAccept(ParameterBag $params)
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canAccept($error))
		{
			return $this->error(\XF::phraseDeferred('dbtech_shop_trade_cannot_be_accepted'));
		}
		
		if ($this->isPost())
		{
			$accepter = $this->setupTradeAccept($trade);
			
			if (!$accepter->validate($errors))
			{
				return $this->error($errors);
			}
			
			$accepter->save();
			
			if ($trade->hasBothUsersAccepted())
			{
				$finalize = $this->setupTradeFinalize($trade);
				
				if (!$finalize->validate($errors))
				{
					return $this->error($errors);
				}
				
				$finalize->save();
			}
			
			return $this->redirect(
				$this->buildLink('dbtech-shop/trades'),
				\XF::phrase('dbtech_shop_trade_accepted_waiting')
			);
		}
		
		$viewParams = [
			'trade' => $trade
		];
		return $this->view('DBTech\Shop:Trade\Accept', 'dbtech_shop_trade_accept', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCancel(ParameterBag $params)
	{
		$trade = $this->assertViewableTrade($params->trade_id);
		
		if (!$trade->canCancel($error))
		{
			return $this->error($error ?: \XF::phraseDeferred('dbtech_shop_trade_cannot_be_cancelled'));
		}
		
		if ($this->isPost())
		{
			$cancelService = $this->setupTradeCancel($trade);
			
			if (!$cancelService->validate($errors))
			{
				return $this->error($errors);
			}
			
			$cancelService->save();
			
			return $this->redirect(
				$this->buildLink('dbtech-shop/trades'),
				\XF::phrase('dbtech_shop_trade_cancelled')
			);
		}
		
		$viewParams = [
			'trade' => $trade
		];
		return $this->view('DBTech\Shop:Trade\Cancel', 'dbtech_shop_trade_cancel', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertPostOnly();
		$trade = $this->assertViewableTrade($params->trade_id);
		if (!$trade->canPostInTrade())
		{
			return $this->noPermission();
		}
		
		$creator = $this->setupTradePostCreate($trade);
		$creator->checkForSpam();
		
		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}
		$this->assertNotFlooding('post');
		$tradePost = $creator->save();
		
		$this->finalizeTradePostCreate($creator);
		
		if ($this->filter('_xfWithData', 'bool') && $this->request->exists('last_date') && $tradePost->canView())
		{
			$tradePostRepo = $this->getTradePostRepo();
			
			$limit = 3;
			$lastDate = $this->filter('last_date', 'uint');
			$style = $this->filter('style', 'str');
			$context = $this->filter('context', 'str');
			$firstUnshownTradePost = null;
			
			if ($context == 'all')
			{
				/** @var \XF\Mvc\Entity\Finder $tradePostList */
				$tradePostList = $tradePostRepo->findNewestTradePosts($lastDate)->with('fullTrade');
				$tradePosts = $tradePostList->fetch($limit)->filterViewable();
			}
			else
			{
				/** @var \XF\Mvc\Entity\Finder $tradePostList */
				$tradePostList = $tradePostRepo->findNewestTradePostsInTrade($trade, $lastDate)->with('fullTrade');
				$tradePosts = $tradePostList->fetch($limit + 1)->filterViewable();
				
				// We fetched one more post than needed, if more than $limit posts were returned,
				// we can show the 'there are more posts' notice
				if ($tradePosts->count() > $limit)
				{
					$firstUnshownTradePost = $tradePosts->last();
					
					// Remove the extra post
					$tradePosts = $tradePosts->pop();
				}
			}
			
			// put the posts into oldest-first order as they will be (essentially prepended) in that order
			$tradePosts = $tradePosts->reverse(true);
			
			$viewParams = [
				'trade' => $trade,
				'style' => $style,
				'tradePosts' => $tradePosts,
				'firstUnshownTradePost' => $firstUnshownTradePost
			];
			$view = $this->view('DBTech\Shop:Trade\NewTradePosts', 'dbtech_shop_trade_post_new_trade_posts', $viewParams);
			$view->setJsonParam('lastDate', $tradePosts->last()->post_date);
			return $view;
		}
		else
		{
			return $this->redirect($this->buildLink('dbtech-shop/trade-posts', $tradePost), \XF::phrase('your_message_has_been_posted'));
		}
	}
	
	/**
	 * @return \DBTech\Shop\Service\Trade\Creator
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupTradeCreate(): \DBTech\Shop\Service\Trade\Creator
	{
		$userName = $this->filter('username', 'str');
		
		/** @var \XF\Entity\User $user */
		$user = $this->finder('XF:User')->where('username', $userName)->fetchOne();
		if (!$user)
		{
			throw $this->exception(
				$this->notFound(\XF::phrase('requested_user_x_not_found', ['name' => $userName]))
			);
		}
		
		/** @var \DBTech\Shop\Service\Trade\Creator $creator */
		$creator = $this->service('DBTech\Shop:Trade\Creator');
		$creator->setRecipient($user);
		$creator->setSendAlert(true, $this->filter('message', 'str'));
		
		return $creator;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\Trade\AcceptInvite
	 */
	protected function setupTradeInviteAccept(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\Trade\AcceptInvite
	{
		/** @var \DBTech\Shop\Service\Trade\AcceptInvite $inviter */
		$inviter = $this->service('DBTech\Shop:Trade\AcceptInvite', $trade);
		$inviter->setSendAlert(true);
		
		return $inviter;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\Trade\Editor
	 */
	protected function setupTradeEdit(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\Trade\Editor
	{
		/** @var \DBTech\Shop\Service\Trade\Editor $editor */
		$editor = $this->service('DBTech\Shop:Trade\Editor', $trade);
		$editor->setSendAlert($trade->trade_state != 'pending');
		
		/**
		 * offers[content_type][content_id][quantity]
		 */
		$tradeOffers = $this->filter('offers', 'array');
		foreach ($tradeOffers as $contentType => $offers)
		{
			foreach ($offers as $contentId => $quantity)
			{
				$editor->addOffer($contentType, $contentId, $quantity);
			}
		}
		
		return $editor;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\Trade\Accept
	 */
	protected function setupTradeAccept(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\Trade\Accept
	{
		/** @var \DBTech\Shop\Service\Trade\Accept $accepter */
		$accepter = $this->service('DBTech\Shop:Trade\Accept', $trade);
		$accepter->setSendAlert(true);
		
		return $accepter;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\Trade\Finalize
	 */
	protected function setupTradeFinalize(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\Trade\Finalize
	{
		/** @var \DBTech\Shop\Service\Trade\Finalize $finalize */
		$finalize = $this->service('DBTech\Shop:Trade\Finalize', $trade);
		$finalize->setSendAlert(true);
		
		return $finalize;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\Trade\Cancel
	 */
	protected function setupTradeCancel(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\Trade\Cancel
	{
		/** @var \DBTech\Shop\Service\Trade\Cancel $cancelService */
		$cancelService = $this->service('DBTech\Shop:Trade\Cancel', $trade);
		
		if ($this->filter('other_user_alert', 'bool'))
		{
			$cancelService->setSendAlert(true, $this->filter('other_user_alert_reason', 'str'));
		}
		
		return $cancelService;
	}
	
	
	
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\TradePost\Creator
	 */
	protected function setupTradePostCreate(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\TradePost\Creator
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');
		
		/** @var \DBTech\Shop\Service\TradePost\Creator $creator */
		$creator = $this->service('DBTech\Shop:TradePost\Creator', $trade);
		$creator->setContent($message);
		
		return $creator;
	}
	
	/**
	 * @param \DBTech\Shop\Service\TradePost\Creator $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeTradePostCreate(\DBTech\Shop\Service\TradePost\Creator $creator)
	{
		$creator->sendNotifications();
		
		$tradePost = $creator->getTradePost();
		
		if (\XF::visitor()->user_id)
		{
			if ($tradePost->message_state == 'moderated')
			{
				$this->session()->setHasContentPendingApproval();
			}
		}
	}
	
	/**
	 * @param array $activities
	 *
	 * @return bool|\XF\Phrase
	 */
	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('dbtech_shop_viewing_trades');
	}
	
	/**
	 * @param int|null $tradeId
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Trade
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTrade(?int $tradeId, $with = null, ?string $phraseKey = null): \DBTech\Shop\Entity\Trade
	{
		/** @var \DBTech\Shop\Entity\Trade $trade */
		$trade = $this->em()->find('DBTech\Shop:Trade', $tradeId, $with);
		if (!$trade)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_page_not_found')));
		}
		
		$canView = $trade->canView();
		if (!$canView)
		{
			throw $this->exception($this->noPermission());
		}
		
		return $trade;
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Trade|\XF\Mvc\Entity\Repository
	 */
	protected function getTradeRepo()
	{
		return $this->repository('DBTech\Shop:Trade');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\TradePost|\XF\Mvc\Entity\Repository
	 */
	protected function getTradePostRepo()
	{
		return $this->repository('DBTech\Shop:TradePost');
	}
}
<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradeOffer;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\TradePostRepository;
use DBTech\Shop\Repository\TradeRepository;
use DBTech\Shop\Service\Trade\AcceptInviteService;
use DBTech\Shop\Service\Trade\AcceptService;
use DBTech\Shop\Service\Trade\CancelService;
use DBTech\Shop\Service\Trade\EditorService;
use DBTech\Shop\Service\Trade\FinalizeService;
use DBTech\Shop\Service\TradePost\CreatorService;
use DBTech\Shop\XF\Entity\User;
use XF\ControllerPlugin\EditorPlugin;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\Pub\Controller\AbstractController;
use XF\Repository\UnfurlRepository;
use XF\Repository\UserAlertRepository;

class TradeController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		if (!\XF::app()->options()->dbtech_shop_trade_enabled)
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

		if (!$visitor->canUseDbtechShopTrade($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		if ($params->trade_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}

		return $this->rerouteController(__CLASS__, 'pending');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionPending(): AbstractReply
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Trade> $trades */
		$trades = \XF::app()->repository(TradeRepository::class)
			->findParticipatingPendingTrades()
			->fetch()
		;

		$viewParams = [
			'selectedTab' => 'pending',
			'trades' => $trades,
			'phrase' => \XF::phrase('dbtech_shop_no_pending_trades_frontend'),
		];

		if ($this->filter('_xfWithData', 'bool'))
		{
			// From AJAX
			$viewParams['_noWrap'] = true;
		}

		return $this->view(
			View\Trade\ListingView::class,
			'dbtech_shop_trade_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCompleted(): AbstractReply
	{
		$finder = \XF::app()->repository(TradeRepository::class)
			->findParticipatingCompletedTrades()
		;

		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopTradesPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/trades/completed');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/trades/completed', null, ['page' => $page]));

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Trade> $trades */
		$trades = $finder->limitByPage($page, $perPage)->fetch();
		$trades = $trades->filterViewable();

		$viewParams = [
			'selectedTab' => 'completed',
			'trades' => $trades,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'phrase' => \XF::phrase('dbtech_shop_no_past_trades_frontend'),
		];

		if ($this->filter('_xfWithData', 'bool'))
		{
			// From AJAX
			$viewParams['_noWrap'] = true;
		}

		return $this->view(
			View\Trade\ListingView::class,
			'dbtech_shop_trade_list',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAdd(): AbstractReply
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

		return $this->view(
			View\Trade\AddView::class,
			'dbtech_shop_trade_add'
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAcceptInvite(ParameterBag $params): AbstractReply
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
			'trade' => $trade,
		];
		return $this->view(
			View\Trade\AcceptInviteView::class,
			'dbtech_shop_trade_accept_invite',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionView(ParameterBag $params): AbstractReply
	{
		$trade = $this->assertViewableTrade($params->trade_id);

		if (!$trade->canView())
		{
			throw $this->exception($this->noPermission());
		}

		$tradeRepo = \XF::app()->repository(TradeRepository::class);
		$offers = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradeOffer> $offersByContentType */
		$offersByContentType = $trade->Offers;
		$offersByContentType = $offersByContentType->groupBy('content_type');

		foreach ($tradeRepo->getTradeOfferHandlers() AS $contentType => $handler)
		{
			$offers[$contentType] = [];

			if (empty($offersByContentType[$contentType]))
			{
				continue;
			}

			/** @var TradeOffer $offer */
			foreach ($offersByContentType[$contentType] AS $offer)
			{
				if (!isset($offers[$contentType][$offer->user_id]))
				{
					$offers[$contentType][$offer->user_id] = [];
				}

				$offers[$contentType][$offer->user_id][] = $offer;
			}
		}

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->messagesPerPage;

		$userAlertRepo = \XF::app()->repository(UserAlertRepository::class);

		if ($trade->canViewPostsInTrade())
		{
			$tradeRepo = \XF::app()->repository(TradePostRepository::class);
			$tradePostFinder = $tradeRepo->findTradePostsInTrade($trade, [
				'allowOwnPending' => $this->hasContentPendingApproval(),
			]);
			$tradePosts = $tradePostFinder->limitByPage($page, $perPage)->fetch();

			$total = $tradePostFinder->total();

			$isRobot = $this->isRobot();
			$tradePosts = $tradeRepo->addCommentsToTradePosts($tradePosts, $isRobot);

			$unfurlRepo = \XF::app()->repository(UnfurlRepository::class);
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
			$tradePosts = \XF::app()->em()->getEmptyCollection();
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
			'total' => $total,
		];
		return $this->view(
			View\Trade\ViewView::class,
			'dbtech_shop_trade_view',
			$viewParams
		);
	}



	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionViewOffer(ParameterBag $params): AbstractReply
	{
		$trade = $this->assertViewableTrade($params->trade_id);

		if (!$trade->canView())
		{
			throw $this->exception($this->noPermission());
		}

		$contentType = $this->filter('content_type', 'str');
		$contentId = $this->filter('content_id', 'uint');
		$userId = $this->filter('user_id', 'uint');

		$handler = \XF::app()->repository(TradeRepository::class)
			->getTradeOfferHandler($contentType)
		;
		if (!$handler)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_page_not_found')));
		}

		/** @var TradeOffer $tradeOffer */
		$tradeOffer = $trade->getRelationFinder('Offers')
			->where('content_type', $contentType)
			->where('content_id', $contentId)
			->where('user_id', $userId)
			->fetchOne()
		;

		$viewParams = [
			'trade' => $trade,
			'tradeOffer' => $tradeOffer,
			'handler' => $handler,
		];
		return $this->view(
			View\Trade\ViewOfferView::class,
			'dbtech_shop_trade_view_offer',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionModify(ParameterBag $params): AbstractReply
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

		$tradeRepo = \XF::app()->repository(TradeRepository::class);

		$offers = $tradeRepo->getGroupedOffersFromTrade($trade);
		$handlers = $tradeRepo->getTradeOfferHandlers();

		$viewParams = [
			'trade' => $trade,
			'offers' => $offers,
			'handlers' => $handlers,
		];
		return $this->view(
			View\Trade\ModifyView::class,
			'dbtech_shop_trade_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionSave(ParameterBag $params): AbstractReply
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAccept(ParameterBag $params): AbstractReply
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
			'trade' => $trade,
		];
		return $this->view(
			View\Trade\AcceptView::class,
			'dbtech_shop_trade_accept',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCancel(ParameterBag $params): AbstractReply
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
			'trade' => $trade,
		];
		return $this->view(
			View\Trade\CancelView::class,
			'dbtech_shop_trade_cancel',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionPost(ParameterBag $params): AbstractReply
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

		if ($this->filter('_xfWithData', 'bool')
			&& $this->request->exists('last_date')
			&& $tradePost->canView()
		)
		{
			$tradePostRepo = \XF::app()->repository(TradePostRepository::class);

			$limit = 3;
			$lastDate = $this->filter('last_date', 'uint');
			$style = $this->filter('style', 'str');
			$context = $this->filter('context', 'str');
			$firstUnshownTradePost = null;

			if ($context == 'all')
			{
				/** @var Finder $tradePostList */
				$tradePostList = $tradePostRepo->findNewestTradePosts($lastDate)->with('fullTrade');
				$tradePosts = $tradePostList->fetch($limit)->filterViewable();
			}
			else
			{
				/** @var Finder $tradePostList */
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
			$tradePosts = $tradePosts->reverse();

			$viewParams = [
				'trade' => $trade,
				'style' => $style,
				'tradePosts' => $tradePosts,
				'firstUnshownTradePost' => $firstUnshownTradePost,
			];
			$view = $this->view(
				View\Trade\NewTradePostsView::class,
				'dbtech_shop_trade_post_new_trade_posts',
				$viewParams
			);
			$view->setJsonParam('lastDate', $tradePosts->last()->post_date);
			return $view;
		}
		else
		{
			return $this->redirect($this->buildLink('dbtech-shop/trade-posts', $tradePost), \XF::phrase('your_message_has_been_posted'));
		}
	}

	/**
	 * @return \DBTech\Shop\Service\Trade\CreatorService
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function setupTradeCreate(): \DBTech\Shop\Service\Trade\CreatorService
	{
		$userName = $this->filter('username', 'str');

		$user = \XF::app()->finder(UserFinder::class)->where('username', $userName)->fetchOne();
		if (!$user)
		{
			throw $this->exception(
				$this->notFound(\XF::phrase('requested_user_x_not_found', ['name' => $userName]))
			);
		}

		$creator = \XF::app()->service(\DBTech\Shop\Service\Trade\CreatorService::class);
		$creator->setRecipient($user);
		$creator->setSendAlert(true, $this->filter('message', 'str'));

		return $creator;
	}

	/**
	 * @param Trade $trade
	 *
	 * @return AcceptInviteService
	 */
	protected function setupTradeInviteAccept(Trade $trade): AcceptInviteService
	{
		$inviter = \XF::app()->service(AcceptInviteService::class, $trade);
		$inviter->setSendAlert(true);

		return $inviter;
	}

	/**
	 * @param Trade $trade
	 *
	 * @return EditorService
	 */
	protected function setupTradeEdit(Trade $trade): EditorService
	{
		$editor = \XF::app()->service(EditorService::class, $trade);
		$editor->setSendAlert($trade->trade_state != 'pending');

		/**
		 * offers[content_type][content_id][quantity]
		 */
		$tradeOffers = $this->filter('offers', 'array');
		foreach ($tradeOffers AS $contentType => $offers)
		{
			foreach ($offers AS $contentId => $quantity)
			{
				$editor->addOffer($contentType, $contentId, $quantity);
			}
		}

		return $editor;
	}

	/**
	 * @param Trade $trade
	 *
	 * @return AcceptService
	 */
	protected function setupTradeAccept(Trade $trade): AcceptService
	{
		$accepter = \XF::app()->service(AcceptService::class, $trade);
		$accepter->setSendAlert(true);

		return $accepter;
	}

	/**
	 * @param Trade $trade
	 *
	 * @return FinalizeService
	 */
	protected function setupTradeFinalize(Trade $trade): FinalizeService
	{
		$finalize = \XF::app()->service(FinalizeService::class, $trade);
		$finalize->setSendAlert(true);

		return $finalize;
	}

	/**
	 * @param Trade $trade
	 *
	 * @return CancelService
	 */
	protected function setupTradeCancel(Trade $trade): CancelService
	{
		$cancelService = \XF::app()->service(CancelService::class, $trade);

		if ($this->filter('other_user_alert', 'bool'))
		{
			$cancelService->setSendAlert(true, $this->filter('other_user_alert_reason', 'str'));
		}

		return $cancelService;
	}



	/**
	 * @param Trade $trade
	 *
	 * @return CreatorService
	 */
	protected function setupTradePostCreate(Trade $trade): CreatorService
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		$creator = \XF::app()->service(CreatorService::class, $trade);
		$creator->setContent($message);

		return $creator;
	}

	/**
	 * @param CreatorService $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeTradePostCreate(CreatorService $creator): void
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
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_trades');
	}

	/**
	 * @param int|null $tradeId
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Trade
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTrade(?int $tradeId, array $with = [], ?string $phraseKey = null): Trade
	{
		$trade = \XF::app()->em()->find(Trade::class, $tradeId, $with);
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
}
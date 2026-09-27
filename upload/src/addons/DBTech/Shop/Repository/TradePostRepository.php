<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Finder\TradePostCommentFinder;
use DBTech\Shop\Finder\TradePostFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XF\Repository\UnfurlRepository;
use XF\Repository\UserAlertRepository;

class TradePostRepository extends Repository
{
	/**
	 * @param Trade $trade
	 * @param array $limits
	 *
	 * @return TradePostFinder
	 */
	public function findTradePostsInTrade(
		Trade $trade,
		array $limits = []
	): TradePostFinder
	{
		/** @var TradePostFinder $finder */
		$finder = \XF::app()->finder(TradePostFinder::class);
		$finder
			->onTrade($trade, $limits)
			->order('post_date', 'DESC')
		;

		return $finder;
	}

	/**
	 * @param Trade $trade
	 * @param int $newerThan
	 * @param array $limits
	 *
	 * @return TradePostFinder
	 */
	public function findNewestTradePostsInTrade(
		Trade $trade,
		int $newerThan,
		array $limits = []
	): TradePostFinder
	{
		/** @var TradePostFinder $finder */
		$finder = $this->findNewestTradePosts($newerThan)
			->onTrade($trade, $limits)
		;

		return $finder;
	}

	/**
	 * @param int $newerThan
	 *
	 * @return TradePostFinder
	 */
	public function findNewestTradePosts(int $newerThan): TradePostFinder
	{
		/** @var TradePostFinder $finder */
		$finder = \XF::app()->finder(TradePostFinder::class);
		$finder
			->newerThan($newerThan)
			->order('post_date', 'DESC')
		;

		return $finder;
	}

	/**
	 * @param TradePost $tradePost
	 * @param array $limits
	 *
	 * @return TradePostCommentFinder
	 */
	public function findTradePostComments(
		TradePost $tradePost,
		array $limits = []
	): TradePostCommentFinder
	{
		/** @var TradePostCommentFinder $commentFinder */
		$commentFinder = \XF::app()->finder(TradePostCommentFinder::class);
		$commentFinder->setDefaultOrder('comment_date');
		$commentFinder->forTradePost($tradePost, $limits);

		return $commentFinder;
	}

	/**
	 * @param TradePost $tradePost
	 * @param int $newerThan
	 * @param array $limits
	 *
	 * @return TradePostCommentFinder
	 */
	public function findNewestCommentsForTradePost(
		TradePost $tradePost,
		int $newerThan,
		array $limits = []
	): TradePostCommentFinder
	{
		/** @var TradePostCommentFinder $commentFinder */
		$commentFinder = \XF::app()->finder(TradePostCommentFinder::class);
		$commentFinder
			->setDefaultOrder('comment_date', 'DESC')
			->forTradePost($tradePost, $limits)
			->newerThan($newerThan)
		;

		return $commentFinder;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradePost> $tradePosts
	 * @param bool $skipUnfurlRecrawl
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradePost>
	 */
	public function addCommentsToTradePosts(
		AbstractCollection $tradePosts,
		bool                              $skipUnfurlRecrawl = false
	): AbstractCollection
	{
		$commentFinder = \XF::app()->finder(TradePostCommentFinder::class);

		$visitor = \XF::visitor();

		$ids = [];
		foreach ($tradePosts AS $tradePost)
		{
			$commentIds = $tradePost->latest_comment_ids;
			foreach ($commentIds AS $commentId => $state)
			{
				$commentId = intval($commentId);

				switch ($state[0])
				{
					case 'visible':
						$ids[] = $commentId;
						break;

					case 'moderated':
						if ($tradePost->canViewModeratedComments())
						{
							// can view all moderated comments
							$ids[] = $commentId;
						}
						else if ($visitor->user_id && $visitor->user_id == $state[1])
						{
							// can view your own moderated comments
							$ids[] = $commentId;
						}
						break;

					case 'deleted':
						if ($tradePost->canViewDeletedComments())
						{
							$ids[] = $commentId;

							$commentFinder->with('DeletionLog');
						}
						break;
				}
			}
		}

		if ($ids)
		{
			$commentFinder->with('full');

			$comments = $commentFinder
				->where('trade_post_comment_id', $ids)
				->order('comment_date')
				->fetch()
			;

			$unfurlRepo = \XF::app()->repository(UnfurlRepository::class);
			$unfurlRepo->addUnfurlsToContent($comments, $skipUnfurlRecrawl);

			$comments = $comments->groupBy('trade_post_id');

			foreach ($tradePosts AS $tradePost)
			{
				$tradePostComments = $comments[$tradePost->trade_post_id] ?? [];
				$tradePostComments = \XF::app()->em()->getBasicCollection($tradePostComments)
					->filterViewable()
					->slice(-3, 3)
				;

				$tradePost->setLatestComments($tradePostComments->toArray());
			}
		}

		return $tradePosts;
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return TradePost
	 */
	public function addCommentsToTradePost(TradePost $tradePost): TradePost
	{
		$id = $tradePost->trade_post_id;
		$result = $this->addCommentsToTradePosts(
			\XF::app()->em()->getBasicCollection([$id => $tradePost])
		);
		return $result[$id];
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return array
	 */
	public function getLatestCommentCache(TradePost $tradePost): array
	{
		$comments = \XF::app()->finder(TradePostCommentFinder::class)
			->where('trade_post_id', $tradePost->trade_post_id)
			->order('comment_date', 'DESC')
			->limit(20)
			->fetch()
		;

		$visCount = 0;
		$latestComments = [];

		/** @var TradePostComment $comment */
		foreach ($comments AS $commentId => $comment)
		{
			if ($comment->message_state == 'visible')
			{
				$visCount++;
			}

			$latestComments[$commentId] = [$comment->message_state, $comment->user_id];

			if ($visCount === 3)
			{
				break;
			}
		}

		return array_reverse($latestComments, true);
	}

	/**
	 * @param TradePost $tradePost
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		TradePost $tradePost,
		string $action,
		string $reason = '',
		array $extra = []
	): bool
	{
		if (!$tradePost->user_id || !$tradePost->User)
		{
			return false;
		}

		$router = \XF::app()->router('public');

		$extra = array_merge(
			[
				'tradeId'             => $tradePost->trade_id,
				'trade'               => $tradePost->Trade ? $tradePost->Trade->title : '',
				'tradeLink'           => $router->buildLink('nopath:dbtech-shop/trades', $tradePost->Trade),
				'link'                => $router->buildLink('nopath:dbtech-shop/trade-posts', $tradePost),
				'reason'              => $reason,
				'depends_on_addon_id' => 'DBTech/Shop',
			],
			$extra
		);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$tradePost->User,
			0,
			'',
			'user',
			$tradePost->user_id,
			"dbt_shop_trade_post_$action",
			$extra
		);

		return true;
	}

	/**
	 * @param TradePostComment $comment
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendCommentModeratorActionAlert(
		TradePostComment $comment,
		string $action,
		string $reason = '',
		array $extra = []
	): bool
	{
		if (!$comment->user_id || !$comment->User)
		{
			return false;
		}

		/** @var TradePost $tradePost */
		$tradePost = $comment->TradePost;
		if (!$tradePost)
		{
			return false;
		}

		$router = \XF::app()->router('public');

		$extra = array_merge(
			[
				'tradeId'             => $tradePost->trade_id,
				'trade'               => $tradePost->Trade ? $tradePost->Trade->title : '',
				'postUserId'          => $tradePost->user_id,
				'postUser'            => $tradePost->User ? $tradePost->User->username : '',
				'link'                => $router->buildLink('nopath:dbtech-shop/trade-posts/comments', $comment),
				'tradeLink'           => $router->buildLink('nopath:dbtech-shop/trades', $tradePost->Trade),
				'tradePostLink'       => $router->buildLink('nopath:dbtech-shop/trade-posts', $tradePost),
				'reason'              => $reason,
				'depends_on_addon_id' => 'DBTech/Shop',
			],
			$extra
		);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$comment->User,
			0,
			'',
			'user',
			$comment->user_id,
			"dbt_shop_trade_comment_$action",
			$extra
		);

		return true;
	}
}
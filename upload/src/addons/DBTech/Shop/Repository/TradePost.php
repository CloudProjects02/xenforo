<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Repository
 */
class TradePost extends Repository
{
	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\TradePost
	 */
	public function findTradePostsInTrade(
		\DBTech\Shop\Entity\Trade $trade,
		array $limits = []
	): \DBTech\Shop\Finder\TradePost {
		/** @var \DBTech\Shop\Finder\TradePost $finder */
		$finder = $this->finder('DBTech\Shop:TradePost');
		$finder
			->onTrade($trade, $limits)
			->order('post_date', 'DESC')
		;

		return $finder;
	}

	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 * @param int $newerThan
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\TradePost
	 */
	public function findNewestTradePostsInTrade(
		\DBTech\Shop\Entity\Trade $trade,
		int $newerThan,
		array $limits = []
	): \DBTech\Shop\Finder\TradePost {
		/** @var \DBTech\Shop\Finder\TradePost $finder */
		$finder = $this->findNewestTradePosts($newerThan)
			->onTrade($trade, $limits)
		;

		return $finder;
	}

	/**
	 * @param int $newerThan
	 *
	 * @return \DBTech\Shop\Finder\TradePost
	 */
	public function findNewestTradePosts(int $newerThan): \DBTech\Shop\Finder\TradePost
	{
		/** @var \DBTech\Shop\Finder\TradePost $finder */
		$finder = $this->finder('DBTech\Shop:TradePost');
		$finder
			->newerThan($newerThan)
			->order('post_date', 'DESC')
		;

		return $finder;
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\TradePostComment
	 */
	public function findTradePostComments(
		\DBTech\Shop\Entity\TradePost $tradePost,
		array $limits = []
	): \DBTech\Shop\Finder\TradePostComment {
		/** @var \DBTech\Shop\Finder\TradePostComment $commentFinder */
		$commentFinder = $this->finder('DBTech\Shop:TradePostComment');
		$commentFinder->setDefaultOrder('comment_date');
		$commentFinder->forTradePost($tradePost, $limits);

		return $commentFinder;
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 * @param int $newerThan
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\TradePostComment
	 */
	public function findNewestCommentsForTradePost(
		\DBTech\Shop\Entity\TradePost $tradePost,
		int $newerThan,
		array $limits = []
	): \DBTech\Shop\Finder\TradePostComment {
		/** @var \DBTech\Shop\Finder\TradePostComment $commentFinder */
		$commentFinder = $this->finder('DBTech\Shop:TradePostComment');
		$commentFinder
			->setDefaultOrder('comment_date', 'DESC')
			->forTradePost($tradePost, $limits)
			->newerThan($newerThan)
		;

		return $commentFinder;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\TradePost[] $tradePosts
	 * @param bool $skipUnfurlRecrawl
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\TradePost[]
	 */
	public function addCommentsToTradePosts(
		$tradePosts,
		bool $skipUnfurlRecrawl = false
	) {
		$commentFinder = $this->finder('DBTech\Shop:TradePostComment');

		$visitor = \XF::visitor();

		$ids = [];
		foreach ($tradePosts as $tradePostId => $tradePost)
		{
			$commentIds = $tradePost->latest_comment_ids;
			foreach ($commentIds as $commentId => $state)
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
						elseif ($visitor->user_id && $visitor->user_id == $state[1])
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

			/** @var \XF\Repository\Unfurl $unfurlRepo */
			$unfurlRepo = $this->repository('XF:Unfurl');
			$unfurlRepo->addUnfurlsToContent($comments, $skipUnfurlRecrawl);

			$comments = $comments->groupBy('trade_post_id');

			foreach ($tradePosts as $tradePostId => $tradePost)
			{
				$tradePostComments = isset($comments[$tradePostId]) ? $comments[$tradePostId] : [];
				$tradePostComments = $this->em->getBasicCollection($tradePostComments)
					->filterViewable()
					->slice(-3, 3)
				;

				$tradePost->setLatestComments($tradePostComments->toArray());
			}
		}

		return $tradePosts;
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Entity\TradePost|mixed
	 */
	public function addCommentsToTradePost(\DBTech\Shop\Entity\TradePost $tradePost): \DBTech\Shop\Entity\TradePost
	{
		$id = $tradePost->trade_post_id;
		$result = $this->addCommentsToTradePosts([$id => $tradePost]);
		return $result[$id];
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return array
	 */
	public function getLatestCommentCache(\DBTech\Shop\Entity\TradePost $tradePost): array
	{
		$comments = $this->finder('DBTech\Shop:TradePostComment')
			->where('trade_post_id', $tradePost->trade_post_id)
			->order('comment_date', 'DESC')
			->limit(20)
			->fetch()
		;

		$visCount = 0;
		$latestComments = [];

		/** @var \DBTech\Shop\Entity\TradePostComment $comment */
		foreach ($comments as $commentId => $comment)
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
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 * @param $action
	 * @param string $reason
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		\DBTech\Shop\Entity\TradePost $tradePost,
		string $action,
		string $reason = '',
		array $extra = []
	): bool {
		if (!$tradePost->user_id || !$tradePost->User)
		{
			return false;
		}

		$router = $this->app()->router('public');

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

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$tradePost->User,
			0,
			'',
			'user',
			$tradePost->user_id,
			"dbt_shop_trade_post_{$action}",
			$extra
		);

		return true;
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendCommentModeratorActionAlert(
		\DBTech\Shop\Entity\TradePostComment $comment,
		string $action,
		string $reason = '',
		array $extra = []
	): bool {
		if (!$comment->user_id || !$comment->User)
		{
			return false;
		}

		/** @var \DBTech\Shop\Entity\TradePost $tradePost */
		$tradePost = $comment->TradePost;
		if (!$tradePost)
		{
			return false;
		}

		$router = $this->app()->router('public');

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

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$comment->User,
			0,
			'',
			'user',
			$comment->user_id,
			"dbt_shop_trade_comment_{$action}",
			$extra
		);

		return true;
	}
}
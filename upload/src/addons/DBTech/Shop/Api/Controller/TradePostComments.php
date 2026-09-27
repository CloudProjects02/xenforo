<?php

namespace DBTech\Shop\Api\Controller;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Service\TradePostComment\CreatorService;
use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * @api-group Trade posts
 */
class TradePostComments extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertApiScopeByRequestMethod('dbtech_shop_trade_post');
	}

	/**
	 * @api-desc Creates a new trade post comment.
	 *
	 * @api-in int $trade_post_id <req> The ID of the trade post this comment will be attached to.
	 * @api-in str $message <req>
	 *
	 * @api-out true $success
	 * @api-out TradePostComment $comment
	 *
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionPost(ParameterBag $params): AbstractReply
	{
		$this->assertRequiredApiInput(['trade_post_id', 'message']);
		$this->assertRegisteredUser();

		$tradePostId = $this->filter('trade_post_id', 'uint');

		/** @var TradePost $tradePost */
		$tradePost = $this->assertViewableApiRecord(TradePost::class, $tradePostId);

		if (\XF::isApiCheckingPermissions() && !$tradePost->canComment($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupNewTradePostComment($tradePost);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var TradePostComment $comment */
		$comment = $creator->save();
		$this->finalizeNewTradePostComment($creator);

		return $this->apiSuccess([
			'comment' => $comment->toApiResult(),
		]);
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return CreatorService
	 */
	protected function setupNewTradePostComment(TradePost $tradePost): CreatorService
	{
		$creator = \XF::app()->service(CreatorService::class, $tradePost);

		$message = $this->filter('message', 'str');
		$creator->setContent($message);

		return $creator;
	}

	/**
	 * @param CreatorService $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeNewTradePostComment(CreatorService $creator): void
	{
		$creator->sendNotifications();
	}
}
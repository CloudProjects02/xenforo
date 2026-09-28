<?php

namespace DBTech\Shop\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * @api-group Trade posts
 */
class TradePostComments extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
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
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertRequiredApiInput(['trade_post_id', 'message']);
		$this->assertRegisteredUser();

		$tradePostId = $this->filter('trade_post_id', 'uint');

		/** @var \DBTech\Shop\Entity\TradePost $tradePost */
		$tradePost = $this->assertViewableApiRecord('DBTech\Shop:TradePost', $tradePostId);

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

		/** @var \DBTech\Shop\Entity\TradePostComment $comment */
		$comment = $creator->save();
		$this->finalizeNewTradePostComment($creator);

		return $this->apiSuccess([
			'comment' => $comment->toApiResult()
		]);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Service\TradePostComment\Creator
	 */
	protected function setupNewTradePostComment(\DBTech\Shop\Entity\TradePost $tradePost): \DBTech\Shop\Service\TradePostComment\Creator
	{
		/** @var \DBTech\Shop\Service\TradePostComment\Creator $creator */
		$creator = $this->service('DBTech\Shop:TradePostComment\Creator', $tradePost);

		$message = $this->filter('message', 'str');
		$creator->setContent($message);

		return $creator;
	}

	/**
	 * @param \DBTech\Shop\Service\TradePostComment\Creator $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeNewTradePostComment(\DBTech\Shop\Service\TradePostComment\Creator $creator)
	{
		$creator->sendNotifications();
	}
}
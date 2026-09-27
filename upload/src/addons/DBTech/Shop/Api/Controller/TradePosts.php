<?php

namespace DBTech\Shop\Api\Controller;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Service\TradePost\CreatorService;
use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;

/**
 * @api-group Trade posts
 */
class TradePosts extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertApiScopeByRequestMethod('dbtech_shop_trade_post');
	}

	/**
	 * @api-desc Creates a new trade post.
	 *
	 * @api-in int $trade)id <req> The ID of the trade this will be posted on.
	 * @api-int str $message <req>
	 *
	 * @param ParameterBag $params
	 *
	 * @return ApiResult|Error
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionPost(ParameterBag $params): ApiResult|Error
	{
		$this->assertRequiredApiInput(['trade_id', 'message']);
		$this->assertRegisteredUser();

		$tradeId = $this->filter('trade_id', 'uint');

		/** @var Trade $trade */
		$trade = $this->assertRecordExists(Trade::class, $tradeId);

		if (\XF::isApiCheckingPermissions())
		{
			if (!$trade->canViewPostsInTrade($error) || !$trade->canPostInTrade())
			{
				throw $this->exception($this->noPermission($error));
			}
		}

		$creator = $this->setupNewTradePost($trade);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var TradePost $tradePost */
		$tradePost = $creator->save();
		$this->finalizeNewTradePost($creator);

		return $this->apiSuccess([
			'trade_post' => $tradePost->toApiResult(),
		]);
	}

	/**
	 * @param Trade $trade
	 *
	 * @return CreatorService
	 */
	protected function setupNewTradePost(Trade $trade): CreatorService
	{
		$creator = \XF::app()->service(CreatorService::class, $trade);

		$message = $this->filter('message', 'str');
		$creator->setContent($message);

		return $creator;
	}

	/**
	 * @param CreatorService $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeNewTradePost(CreatorService $creator): void
	{
		$creator->sendNotifications();
	}
}
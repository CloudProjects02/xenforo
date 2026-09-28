<?php

namespace DBTech\Shop\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * @api-group Trade posts
 */
class TradePosts extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('dbtech_shop_trade_post');
	}

	/**
	 * @api-desc Creates a new trade post.
	 *
	 * @api-in int $trade)id <req> The ID of the trade this will be posted on.
	 * @api-int str $message <req>
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\Error
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertRequiredApiInput(['trade_id', 'message']);
		$this->assertRegisteredUser();

		$tradeId = $this->filter('trade_id', 'uint');

		/** @var \DBTech\Shop\Entity\Trade $trade */
		$trade = $this->assertRecordExists('DBTech\Shop:Trade', $tradeId);

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

		/** @var \DBTech\Shop\Entity\TradePost $tradePost */
		$tradePost = $creator->save();
		$this->finalizeNewTradePost($creator);

		return $this->apiSuccess([
			'trade_post' => $tradePost->toApiResult()
		]);
	}

	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return \DBTech\Shop\Service\TradePost\Creator
	 */
	protected function setupNewTradePost(\DBTech\Shop\Entity\Trade $trade): \DBTech\Shop\Service\TradePost\Creator
	{
		/** @var \DBTech\Shop\Service\TradePost\Creator $creator */
		$creator = $this->service('DBTech\Shop:TradePost\Creator', $trade);

		$message = $this->filter('message', 'str');
		$creator->setContent($message);

		return $creator;
	}

	/**
	 * @param \DBTech\Shop\Service\TradePost\Creator $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeNewTradePost(\DBTech\Shop\Service\TradePost\Creator $creator)
	{
		$creator->sendNotifications();
	}
}
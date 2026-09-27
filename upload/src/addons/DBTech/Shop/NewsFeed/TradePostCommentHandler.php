<?php

namespace DBTech\Shop\NewsFeed;

use XF\NewsFeed\AbstractHandler;

class TradePostCommentHandler extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['TradePost', 'TradePost.Trade'];
	}
}
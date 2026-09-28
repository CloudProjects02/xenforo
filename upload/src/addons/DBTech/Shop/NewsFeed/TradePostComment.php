<?php

namespace DBTech\Shop\NewsFeed;

use XF\NewsFeed\AbstractHandler;

/**
 * Class TradePostComment
 *
 * @package DBTech\Shop\NewsFeed
 */
class TradePostComment extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['TradePost', 'TradePost.Trade'];
	}
}
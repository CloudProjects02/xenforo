<?php

namespace DBTech\Shop\NewsFeed;

use XF\NewsFeed\AbstractHandler;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\NewsFeed
 */
class TradePost extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}
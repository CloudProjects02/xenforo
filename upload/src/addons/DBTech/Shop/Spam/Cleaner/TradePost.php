<?php

namespace DBTech\Shop\Spam\Cleaner;

use DBTech\Shop\Finder\TradePostFinder;
use XF\PrintableException;
use XF\Spam\Cleaner\AbstractHandler;

class TradePost extends AbstractHandler
{
	/**
	 * @param array $options
	 *
	 * @return bool
	 */
	public function canCleanUp(array $options = []): bool
	{
		return !empty($options['delete_messages']);
	}

	/**
	 * @param array $log
	 * @param null $error
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function cleanUp(array &$log, &$error = null): bool
	{
		$app = \XF::app();

		$tradePostsFinder = \XF::app()->finder(TradePostFinder::class);
		$tradePosts = $tradePostsFinder
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($tradePosts->count())
		{
			$tradePostIds = $tradePosts->pluckNamed('trade_post_id');
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('dbtech_shop_trade_post', $tradePostIds);

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['dbtech_shop_trade_post'] = [
				'deleteType' => $deleteType,
				'tradePostIds' => [],
			];

			foreach ($tradePosts AS $tradePostId => $tradePost)
			{
				$log['dbtech_shop_trade_post']['tradePostIds'][] = $tradePostId;

				/** @var \DBTech\Shop\Entity\TradePost $tradePost */
				$tradePost->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$tradePost->softDelete();
				}
				else
				{
					$tradePost->delete();
				}
			}
		}

		return true;
	}

	/**
	 * @param array $log
	 * @param null $error
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function restore(array $log, &$error = null): bool
	{
		$tradePostsFinder = \XF::app()->finder(TradePostFinder::class);

		if ($log['deleteType'] == 'soft')
		{
			$tradePosts = $tradePostsFinder->where('trade_post_id', $log['tradePostIds'])->fetch();
			foreach ($tradePosts AS $tradePost)
			{
				/** @var \DBTech\Shop\Entity\TradePost $tradePost */
				$tradePost->setOption('log_moderator', false);
				$tradePost->message_state = 'visible';
				$tradePost->save();
			}
		}

		return true;
	}
}
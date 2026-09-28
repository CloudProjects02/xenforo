<?php

namespace DBTech\Shop\Spam\Cleaner;

use XF\Spam\Cleaner\AbstractHandler;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Spam\Cleaner
 */
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
	 * @throws \XF\PrintableException
	 */
	public function cleanUp(array &$log, &$error = null): bool
	{
		$app = \XF::app();

		$tradePostsFinder = $app->finder('DBTech\Shop:TradePost');
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
				'tradePostIds' => []
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
	 * @throws \XF\PrintableException
	 */
	public function restore(array $log, &$error = null): bool
	{
		$tradePostsFinder = \XF::app()->finder('DBTech\Shop:TradePost');

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
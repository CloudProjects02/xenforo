<?php

namespace DBTech\Shop\Spam\Cleaner;

use DBTech\Shop\Finder\TradePostCommentFinder;
use XF\PrintableException;
use XF\Spam\Cleaner\AbstractHandler;

class TradePostComment extends AbstractHandler
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

		$tradePostCommentsFinder = \XF::app()->finder(TradePostCommentFinder::class);
		$tradePostComments = $tradePostCommentsFinder
			->where('user_id', $this->user->user_id)
			->fetch();

		if ($tradePostComments->count())
		{
			$tradePostCommentIds = $tradePostComments->pluckNamed('trade_post_comment_id');
			$submitter = $app->container('spam.contentSubmitter');
			$submitter->submitSpam('dbtech_shop_trade_comment', $tradePostCommentIds);

			$deleteType = $app->options()->spamMessageAction == 'delete' ? 'hard' : 'soft';

			$log['dbtech_shop_trade_comment'] = [
				'deleteType' => $deleteType,
				'tradeePostCommentIds' => [],
			];

			foreach ($tradePostComments AS $tradePostCommentId => $tradePostComment)
			{
				$log['dbtech_shop_trade_comment']['tradePostCommentIds'][] = $tradePostCommentId;

				/** @var \DBTech\Shop\Entity\TradePostComment $tradePostComment */
				$tradePostComment->setOption('log_moderator', false);
				if ($deleteType == 'soft')
				{
					$tradePostComment->softDelete();
				}
				else
				{
					$tradePostComment->delete();
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
		$tradePostCommentsFinder = \XF::app()->finder(TradePostCommentFinder::class);

		if ($log['deleteType'] == 'soft')
		{
			$tradePostComments = $tradePostCommentsFinder->where('trade_post_comment_id', $log['tradePostCommentIds'])->fetch();
			foreach ($tradePostComments AS $tradePostComment)
			{
				/** @var \DBTech\Shop\Entity\TradePostComment $tradePostComment */
				$tradePostComment->setOption('log_moderator', false);
				$tradePostComment->message_state = 'visible';
				$tradePostComment->save();
			}
		}

		return true;
	}
}
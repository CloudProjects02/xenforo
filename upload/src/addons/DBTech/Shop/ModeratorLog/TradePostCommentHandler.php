<?php

namespace DBTech\Shop\ModeratorLog;

use DBTech\Shop\Entity\TradePostComment;
use XF\Entity\ModeratorLog;
use XF\Entity\User;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

class TradePostCommentHandler extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param string $action
	 * @param User $actor
	 *
	 * @return bool
	 */
	public function isLoggable(Entity $content, $action, User $actor): bool
	{
		/** @var TradePostComment $content */

		if ($action == 'edit')
		{
			if ($actor->user_id == $content->user_id)
			{
				return false;
			}
		}

		return parent::isLoggable($content, $action, $actor);
	}

	/**
	 * @param Entity $content
	 * @param string $field
	 * @param $newValue
	 * @param $oldValue
	 *
	 * @return array|bool|string
	 */
	protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue): bool|array|string
	{
		/** @var TradePostComment $content */

		switch ($field)
		{
			case 'message':
				return 'edit';

			case 'message_state':
				if ($newValue == 'visible' && $oldValue == 'moderated')
				{
					return 'approve';
				}
				else if ($newValue == 'visible' && $oldValue == 'deleted')
				{
					return 'undelete';
				}
				else if ($newValue == 'deleted')
				{
					$reason = $content->DeletionLog ? $content->DeletionLog->delete_reason : '';
					return ['delete_soft', ['reason' => $reason]];
				}
				else if ($newValue == 'moderated')
				{
					return 'unapprove';
				}
				break;
		}

		return false;
	}

	/**
	 * @param ModeratorLog $log
	 * @param Entity $content
	 */
	protected function setupLogEntityContent(ModeratorLog $log, Entity $content): void
	{
		/** @var TradePostComment $content */
		$log->content_user_id = $content->user_id;
		$log->content_username = $content->username;
		$log->content_title = '';
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:dbtech-shop/trade-posts/comments', $content);
		$log->discussion_content_type = 'dbtech_shop_trade_post';
		$log->discussion_content_id = $content->trade_post_id;
	}

	/**
	 * @param ModeratorLog $log
	 *
	 * @return string
	 */
	public function getContentTitle(ModeratorLog $log): string
	{
		return \XF::phrase('dbtech_shop_trade_post_comment_by_x', [
			'username' => $log->content_username,
		])->render('raw');
	}
}
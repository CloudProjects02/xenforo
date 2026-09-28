<?php

namespace DBTech\Shop\ModeratorLog;

use XF\ModeratorLog\AbstractHandler;
use XF\Entity\ModeratorLog;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePostComment
 *
 * @package DBTech\Shop\ModeratorLog
 */
class TradePostComment extends AbstractHandler
{
	/**
	 * @param Entity $content
	 * @param string $action
	 * @param \XF\Entity\User $actor
	 *
	 * @return bool
	 */
	public function isLoggable(Entity $content, $action, \XF\Entity\User $actor): bool
	{
		/** @var \DBTech\Shop\Entity\TradePostComment $content */
		
		switch ($action)
		{
			case 'edit':
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
	protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
	{
		/** @var \DBTech\Shop\Entity\TradePostComment $content */
		
		switch ($field)
		{
			case 'message':
				return 'edit';

			case 'message_state':
				if ($newValue == 'visible' && $oldValue == 'moderated')
				{
					return 'approve';
				}
				elseif ($newValue == 'visible' && $oldValue == 'deleted')
				{
					return 'undelete';
				}
				elseif ($newValue == 'deleted')
				{
					$reason = $content->DeletionLog ? $content->DeletionLog->delete_reason : '';
					return ['delete_soft', ['reason' => $reason]];
				}
				elseif ($newValue == 'moderated')
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
	protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
	{
		/** @var \DBTech\Shop\Entity\TradePostComment $content */
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
	 * @return mixed|string|string[]|null
	 */
	public function getContentTitle(ModeratorLog $log)
	{
		return \XF::phrase('dbtech_shop_trade_post_comment_by_x', [
			'username' => $log->content_username
		])->render('raw');
	}
}
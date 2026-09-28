<?php

namespace DBTech\Shop\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class ItemRating
 *
 * @package DBTech\Shop\ModeratorLog
 */
class ItemRating extends AbstractHandler
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
		/** @var \DBTech\Shop\Entity\ItemRating $content */
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
		/** @var \DBTech\Shop\Entity\ItemRating $content */
		switch ($field)
		{
			case 'rating_state':
				if ($newValue == 'visible' && $oldValue == 'deleted')
				{
					return 'undelete';
				}
				
				if ($newValue == 'deleted')
				{
					$reason = $content->DeletionLog ? $content->DeletionLog->delete_reason : '';
					return ['delete_soft', ['reason' => $reason]];
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
		/** @var \DBTech\Shop\Entity\ItemRating $content */
		$item = $content->Item;

		$log->content_user_id = $content->user_id;
		$log->content_username = $content->User->username;
		$log->content_title = $item->title;
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:dbtech-shop/review', $content);
		$log->discussion_content_type = 'dbtech_shop_rating';
		$log->discussion_content_id = $content->item_rating_id;
	}
	
	/**
	 * @param ModeratorLog $log
	 *
	 * @return null|string|string[]|\XF\Phrase
	 */
	public function getContentTitle(ModeratorLog $log)
	{
		return \XF::phrase('dbtech_shop_item_review_in_x', [
			'title' => \XF::app()->stringFormatter()->censorText($log->content_title_)
		]);
	}
}
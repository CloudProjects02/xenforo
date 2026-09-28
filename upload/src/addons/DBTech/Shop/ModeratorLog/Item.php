<?php

namespace DBTech\Shop\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\ModeratorLog\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class Item
 *
 * @package DBTech\Shop\ModeratorLog
 */
class Item extends AbstractHandler
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
		/** @var \DBTech\Shop\Entity\Item $content */
		switch ($action)
		{
			case 'prefix_id':
			case 'item_fields':
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
	 * @param mixed $newValue
	 * @param mixed $oldValue
	 *
	 * @return array|bool|string
	 */
	protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue)
	{
		/** @var \DBTech\Shop\Entity\Item $content */
		switch ($field)
		{
			case 'item_fields':
				return 'item_fields_edit';

			case 'item_state':
				if ($newValue == 'visible')
				{
					if ($oldValue == 'moderated')
					{
						return 'approve';
					}
					
					if ($oldValue == 'deleted')
					{
						return 'undelete';
					}
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
			
			case 'prefix_id':
				if ($oldValue)
				{
					$old = \XF::phrase('dbtech_shop_item_prefix.' . $oldValue)->render();
				}
				else
				{
					$old = '-';
				}
				return ['prefix', ['old' => $old]];
			
			case 'category_id':
				/** @var \DBTech\Shop\Entity\Category $category */
				$category = \XF::em()->find('DBTech\Shop:Category', $oldValue);
				$oldTitle = $category ? $category->title : '';
				return ['move', ['from' => $oldTitle]];

			case 'user_id':
				$oldUser = \XF::em()->find('XF:User', $oldValue);
				$from = $oldUser ? $oldUser->username : '';
				return ['reassign', ['from' => $from]];
		}

		return false;
	}
	
	/**
	 * @param ModeratorLog $log
	 * @param Entity $content
	 */
	protected function setupLogEntityContent(ModeratorLog $log, Entity $content)
	{
		/** @var \DBTech\Shop\Entity\Item $content */
		$log->content_user_id = $content->user_id;
		$log->content_username = $content->User->username;
		$log->content_title = $content->title;
		$log->content_url = \XF::app()->router('public')->buildLink('nopath:dbtech-shop', $content);
		$log->discussion_content_type = 'dbtech_shop_item';
		$log->discussion_content_id = $content->item_id;
	}
	
	/**
	 * @param ModeratorLog $log
	 *
	 * @return array
	 */
	protected function getActionPhraseParams(ModeratorLog $log): array
	{
		if ($log->action == 'edit')
		{
			return ['elements' => implode(', ', array_keys($log->action_params))];
		}
		
		return parent::getActionPhraseParams($log);
	}
}
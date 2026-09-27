<?php

namespace DBTech\Credits\ContentAccess;

use DBTech\Credits\XF\Entity\Thread;
use XF\Mvc\Entity\Entity;

class ThreadHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var Thread $entity */

		if ($entity->isVisible() && $entity->dbtech_credits_access_currency_id)
		{
			foreach ($entity->UserPosts AS $userPost)
			{
				\XF::app()->db()->insert('xf_dbtech_credits_content_access_purchase', [
					'content_type' => 'thread',
					'content_id' => $entity->thread_id,
					'user_id' => $userPost->user_id,
				], false, false, 'IGNORE');
			}
		}
	}
}
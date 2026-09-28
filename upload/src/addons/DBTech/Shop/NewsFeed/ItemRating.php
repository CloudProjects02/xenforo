<?php

namespace DBTech\Shop\NewsFeed;

use XF\Mvc\Entity\Entity;
use XF\NewsFeed\AbstractHandler;

/**
 * Class ItemRating
 *
 * @package DBTech\Shop\NewsFeed
 */
class ItemRating extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 * @param $action
	 *
	 * @return bool
	 */
	public function isPublishable(Entity $entity, $action): bool
	{
		/** @var \DBTech\Shop\Entity\ItemRating $entity */
		if (!$entity->is_review)
		{
			return false;
		}

		return true;
	}
	
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Item', 'Item.Permissions|' . $visitor->permission_combination_id, 'Item.User', 'Item.Category', 'Item.Category.Permissions|' . $visitor->permission_combination_id];
	}
}
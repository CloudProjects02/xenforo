<?php

namespace DBTech\Shop\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class Item
 * @package DBTech\Shop\Reaction
 */
class Item extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 * @return mixed
	 */
	public function reactionsCounted(Entity $entity): bool
	{
		/** @var \DBTech\Shop\Entity\Item $entity */
		
		if (!$entity->Category)
		{
			return false;
		}
		
		return $entity->item_state == 'visible';
	}

	/**
	 * @param \XF\Mvc\Entity\Entity $entity
	 *
	 * @return int|mixed|null
	 */
	public function getContentUserId(Entity $entity): ?int
	{
		/** @var \DBTech\Shop\Entity\Item $entity */
		return $entity->user_id;
	}
	
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();
		
		return ['Permissions|' . $visitor->permission_combination_id, 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}
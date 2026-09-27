<?php

namespace DBTech\Shop\Reaction;

use DBTech\Shop\Entity\Item;
use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class ItemHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 * @return mixed
	 */
	public function reactionsCounted(Entity $entity): bool
	{
		/** @var Item $entity */

		if (!$entity->Category)
		{
			return false;
		}

		return $entity->item_state == 'visible';
	}

	/**
	 * @param Entity $entity
	 *
	 * @return int|null
	 */
	public function getContentUserId(Entity $entity): ?int
	{
		/** @var Item $entity */
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
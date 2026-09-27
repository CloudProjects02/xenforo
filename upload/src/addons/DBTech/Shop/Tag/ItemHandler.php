<?php

namespace DBTech\Shop\Tag;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use XF\Mvc\Entity\Entity;
use XF\Tag\AbstractHandler;

class ItemHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	public function getPermissionsFromContext(Entity $entity): array
	{
		if ($entity instanceof Item)
		{
			$item = $entity;
			$category = $item->Category;
		}
		else if ($entity instanceof Category)
		{
			$item = null;
			$category = $entity;
		}
		else
		{
			throw new \InvalidArgumentException('Entity must be a item or category');
		}

		$visitor = \XF::visitor();

		if ($item)
		{
			if ($item->user_id == $visitor->user_id && $item->hasPermission('manageOthersTagsOwnItem'))
			{
				$removeOthers = true;
			}
			else
			{
				$removeOthers = $item->hasPermission('manageAnyTag');
			}

			$edit = $item->canEditTags();
		}
		else
		{
			$removeOthers = false;
			$edit = $category->canEditTags();
		}

		return [
			'edit' => $edit,
			'removeOthers' => $removeOthers,
			'minTotal' => $category->min_tags,
		];
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function getContentVisibility(Entity $entity): bool
	{
		//		return $entity->item_state == 'visible';
		return $entity->isVisible();
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @return array
	 */
	public function getTemplateData(Entity $entity, array $options = []): array
	{
		return [
			'item' => $entity,
			'options' => $options,
		];
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith($forView = false): array
	{
		$get = ['Category'];
		if ($forView)
		{
			$get[] = 'User';

			$visitor = \XF::visitor();
			$get[] = 'Permissions|' . $visitor->permission_combination_id;
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	/**
	 * @param Entity $entity
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(Entity $entity, &$error = null): bool
	{
		/** @var Item $entity */
		return $entity->canUseInlineModeration($error);
	}
}
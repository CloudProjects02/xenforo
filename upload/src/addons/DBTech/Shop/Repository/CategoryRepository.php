<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Finder\CategoryFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\AbstractCategoryTree;
use XF\Tree;

class CategoryRepository extends AbstractCategoryTree
{
	/**
	 * @return string
	 */
	protected function getClassName(): string
	{
		return 'DBTech\Shop:Category';
	}

	/**
	 * @param array $extras
	 * @param array $childExtras
	 *
	 * @return array
	 */
	public function mergeCategoryListExtras(array $extras, array $childExtras): array
	{
		$output = array_merge([
			'childCount' => 0,
			'item_count' => 0,
			'last_update' => 0,
			'last_item_title' => '',
			'last_item_id' => 0,
		], $extras);

		foreach ($childExtras AS $child)
		{
			if (!empty($child['item_count']))
			{
				$output['item_count'] += $child['item_count'];
			}

			if (!empty($child['last_update']) && $child['last_update'] > $output['last_update'])
			{
				$output['last_update'] = $child['last_update'];
				$output['last_item_title'] = $child['last_item_title'];
				$output['last_item_id'] = $child['last_item_id'];
			}

			$output['childCount'] += 1 + (!empty($child['childCount']) ? $child['childCount'] : 0);
		}

		return $output;
	}

	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Category> $entities */
		$entities = \XF::app()->finder(CategoryFinder::class)->fetch();
		foreach ($entities AS $entity)
		{
			$cache[$entity->getIdentifier()] = $entity->toArray(false);
		}

		return $cache;
	}

	/**
	 * @return array
	 */
	public function rebuildCache(): array
	{
		$cache = $this->getCacheData();
		\XF::registry()->set('dbtShopCategories', $cache);
		return $cache;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Category>|null $categories
	 * @param int $rootId
	 *
	 * @return Tree
	 */
	public function getCategoryTree(?AbstractCollection $categories = null, int $rootId = 0): Tree
	{
		return $this->createCategoryTree($categories, $rootId);
	}
}
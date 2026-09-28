<?php

namespace DBTech\Shop\Repository;

use XF\Repository\AbstractCategoryTree;

/**
 * Class Category
 * @package DBTech\Shop\Repository
 */
class Category extends AbstractCategoryTree
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
			'last_item_id' => 0
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
		
		/** @var \DBTech\Shop\Entity\Category[] $entities */
		$entities = $this->finder('DBTech\Shop:Category')->fetch();
		foreach ($entities as $entity)
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
	 * @param \XF\Mvc\Entity\ArrayCollection|null $categories
	 * @param int $rootId
	 *
	 * @return \XF\Tree
	 */
	public function getCategoryTree(?\XF\Mvc\Entity\ArrayCollection $categories = null, int $rootId = 0): \XF\Tree
	{
		return $this->createCategoryTree($categories, $rootId);
	}
}
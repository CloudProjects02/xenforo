<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Category;
use XF\Entity\AbstractPrefix;
use XF\Repository\AbstractPrefixMap;

class CategoryPrefixRepository extends AbstractPrefixMap
{
	/**
	 * @return string
	 */
	protected function getMapEntityIdentifier(): string
	{
		return 'DBTech\Shop:CategoryPrefix';
	}

	/**
	 * @param AbstractPrefix $prefix
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	protected function getAssociationsForPrefix(AbstractPrefix $prefix): mixed
	{
		return $prefix->getRelation('CategoryPrefixes');
	}

	/**
	 * @param array $cache
	 */
	protected function updateAssociationCache(array $cache): void
	{
		$ids = array_keys($cache);
		$categories = \XF::app()->em()->findByIds(Category::class, $ids);

		foreach ($categories AS $category)
		{
			/** @var Category $category */
			$category->prefix_cache = $cache[$category->category_id];
			$category->saveIfChanged();
		}
	}
}
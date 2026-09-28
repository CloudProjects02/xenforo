<?php

namespace DBTech\Shop\Repository;

use XF\Repository\AbstractPrefixMap;

/**
 * Class CategoryPrefix
 *
 * @package DBTech\Shop\Repository
 */
class CategoryPrefix extends AbstractPrefixMap
{
	/**
	 * @return string
	 */
	protected function getMapEntityIdentifier(): string
	{
		return 'DBTech\Shop:CategoryPrefix';
	}
	
	/**
	 * @param \XF\Entity\AbstractPrefix $prefix
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	protected function getAssociationsForPrefix(\XF\Entity\AbstractPrefix $prefix)
	{
		return $prefix->getRelation('CategoryPrefixes');
	}
	
	/**
	 * @param array $cache
	 */
	protected function updateAssociationCache(array $cache)
	{
		$ids = array_keys($cache);
		$categories = $this->em->findByIds('DBTech\Shop:Category', $ids);

		foreach ($categories AS $category)
		{
			/** @var \DBTech\Shop\Entity\Category $category */
			$category->prefix_cache = $cache[$category->category_id];
			$category->saveIfChanged();
		}
	}
}
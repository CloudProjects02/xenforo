<?php

namespace XFRM\Repository;

use XF\Entity\AbstractPrefix;
use XF\Repository\AbstractPrefixMap;
use XFRM\Entity\Category;

class CategoryPrefix extends AbstractPrefixMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XFRM:CategoryPrefix';
	}

	protected function getAssociationsForPrefix(AbstractPrefix $prefix)
	{
		return $prefix->getRelation('CategoryPrefixes');
	}

	protected function updateAssociationCache(array $cache)
	{
		$ids = array_keys($cache);
		$categories = $this->em->findByIds('XFRM:Category', $ids);

		foreach ($categories AS $category)
		{
			/** @var Category $category */
			$category->prefix_cache = $cache[$category->resource_category_id];
			$category->saveIfChanged();
		}
	}
}

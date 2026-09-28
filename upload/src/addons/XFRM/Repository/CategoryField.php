<?php

namespace XFRM\Repository;

use XF\Entity\AbstractField;
use XF\Repository\AbstractFieldMap;
use XFRM\Entity\Category;

class CategoryField extends AbstractFieldMap
{
	protected function getMapEntityIdentifier()
	{
		return 'XFRM:CategoryField';
	}

	protected function getAssociationsForField(AbstractField $field)
	{
		return $field->getRelation('CategoryFields');
	}

	protected function updateAssociationCache(array $cache)
	{
		$categoryIds = array_keys($cache);
		$categories = $this->em->findByIds('XFRM:Category', $categoryIds);

		foreach ($categories AS $category)
		{
			/** @var Category $category */
			$category->field_cache = $cache[$category->resource_category_id];
			$category->saveIfChanged();
		}
	}
}

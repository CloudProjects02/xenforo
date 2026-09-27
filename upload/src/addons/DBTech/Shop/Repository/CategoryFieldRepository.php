<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Category;
use XF\Entity\AbstractField;
use XF\Repository\AbstractFieldMap;

class CategoryFieldRepository extends AbstractFieldMap
{
	/**
	 * @return string
	 */
	protected function getMapEntityIdentifier(): string
	{
		return 'DBTech\Shop:CategoryField';
	}

	/**
	 * @param AbstractField $field
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	protected function getAssociationsForField(AbstractField $field): mixed
	{
		return $field->getRelation('CategoryFields');
	}

	/**
	 * @param array $cache
	 */
	protected function updateAssociationCache(array $cache): void
	{
		$categoryIds = array_keys($cache);
		$categorys = \XF::app()->em()->findByIds(Category::class, $categoryIds);

		foreach ($categorys AS $category)
		{
			/** @var Category $category */
			$category->field_cache = $cache[$category->category_id];
			$category->saveIfChanged();
		}
	}
}
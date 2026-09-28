<?php

namespace DBTech\Shop\Repository;

use XF\Repository\AbstractFieldMap;

/**
 * Class CategoryField
 *
 * @package DBTech\Shop\Repository
 */
class CategoryField extends AbstractFieldMap
{
	/**
	 * @return string
	 */
	protected function getMapEntityIdentifier(): string
	{
		return 'DBTech\Shop:CategoryField';
	}
	
	/**
	 * @param \XF\Entity\AbstractField $field
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	protected function getAssociationsForField(\XF\Entity\AbstractField $field)
	{
		return $field->getRelation('CategoryFields');
	}
	
	/**
	 * @param array $cache
	 */
	protected function updateAssociationCache(array $cache)
	{
		$categoryIds = array_keys($cache);
		$categorys = $this->em->findByIds('DBTech\Shop:Category', $categoryIds);
		
		foreach ($categorys AS $category)
		{
			/** @var \DBTech\Shop\Entity\Category $category */
			$category->field_cache = $cache[$category->category_id];
			$category->saveIfChanged();
		}
	}
}
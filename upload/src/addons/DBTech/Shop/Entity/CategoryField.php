<?php

namespace DBTech\Shop\Entity;

use XF\Entity\AbstractFieldMap;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $category_id
 * @property string $field_id
 *
 * RELATIONS
 * @property-read ItemField|null $Field
 * @property-read Category|null $Category
 */
class CategoryField extends AbstractFieldMap
{
	/**
	 * @return string
	 */
	public static function getContainerKey(): string
	{
		return 'category_id';
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		self::setupDefaultStructure($structure, 'xf_dbtech_shop_category_field', 'DBTech\Shop:CategoryField', 'DBTech\Shop:ItemField');

		$structure->relations['Category'] = [
			'entity' => Category::class,
			'type' => self::TO_ONE,
			'conditions' => 'category_id',
			'primary' => true,
		];

		return $structure;
	}
}
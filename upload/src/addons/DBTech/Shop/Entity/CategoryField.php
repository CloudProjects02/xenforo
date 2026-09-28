<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Structure;
use XF\Entity\AbstractFieldMap;

/**
 * COLUMNS
 * @property int $category_id
 * @property string $field_id
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\ItemField $Field
 * @property \DBTech\Shop\Entity\Category $Category
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
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		self::setupDefaultStructure($structure, 'xf_dbtech_shop_category_field', 'DBTech\Shop:CategoryField', 'DBTech\Shop:ItemField');

		$structure->relations['Category'] = [
			'entity' => 'DBTech\Shop:Category',
			'type' => self::TO_ONE,
			'conditions' => 'category_id',
			'primary' => true
		];

		return $structure;
	}
}
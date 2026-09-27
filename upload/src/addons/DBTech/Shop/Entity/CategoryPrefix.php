<?php

namespace DBTech\Shop\Entity;

use XF\Entity\AbstractPrefixMap;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $category_id
 * @property int $prefix_id
 *
 * RELATIONS
 * @property-read ItemPrefix|null $Prefix
 * @property-read Category|null $Category
 */
class CategoryPrefix extends AbstractPrefixMap
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
		self::setupDefaultStructure($structure, 'xf_dbtech_shop_category_prefix', 'DBTech\Shop:CategoryPrefix', 'DBTech\Shop:ItemPrefix');

		$structure->relations['Category'] = [
			'entity' => Category::class,
			'type' => self::TO_ONE,
			'conditions' => 'category_id',
			'primary' => true,
		];

		return $structure;
	}
}
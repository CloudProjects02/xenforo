<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $item_id
 * @property string $field_id
 * @property string $field_value
 */
class ItemFieldValue extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_item_field_value';
		$structure->shortName = 'DBTech\Shop:ItemFieldValue';
		$structure->primaryKey = ['item_id', 'field_id'];
		$structure->columns = [
			'item_id' => ['type' => self::UINT, 'required' => true],
			'field_id' => ['type' => self::STR, 'maxLength' => 25,
				'match' => 'alphanumeric'
			],
			'field_value' => ['type' => self::STR, 'default' => '']
		];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}
}
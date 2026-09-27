<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $item_id
 * @property string $filter_id
 *
 * RELATIONS
 * @property-read Item|null $Item
 */
class ItemFilterMap extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_item_filter_map';
		$structure->shortName = 'DBTech\Shop:ItemFilterMap';
		$structure->primaryKey = ['item_id', 'filter_id'];
		$structure->columns = [
			'item_id' => ['type' => self::UINT, 'required' => true],
			'filter_id' => ['type' => self::BINARY, 'maxLength' => 25, 'required' => true],
		];
		$structure->getters = [];
		$structure->relations = [
			'Item' => [
				'entity' => Item::class,
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}
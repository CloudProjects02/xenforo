<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property int $item_id
 * @property bool $email_subscribe
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Item $Item
 * @property \XF\Entity\User $User
 */
class ItemWatch extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_item_watch';
		$structure->shortName = 'DBTech\Shop:ItemWatch';
		$structure->primaryKey = ['user_id', 'item_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'item_id' => ['type' => self::UINT, 'required' => true],
			'email_subscribe' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->getters = [];
		$structure->relations = [
			'Item' => [
				'entity' => 'DBTech\Shop:Item',
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
		];

		return $structure;
	}
}
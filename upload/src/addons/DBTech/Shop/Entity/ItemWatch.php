<?php

namespace DBTech\Shop\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property int $item_id
 * @property bool $email_subscribe
 *
 * RELATIONS
 * @property-read Item|null $Item
 * @property-read User|null $User
 */
class ItemWatch extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_item_watch';
		$structure->shortName = 'DBTech\Shop:ItemWatch';
		$structure->primaryKey = ['user_id', 'item_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'item_id' => ['type' => self::UINT, 'required' => true],
			'email_subscribe' => ['type' => self::BOOL, 'default' => false],
		];
		$structure->getters = [];
		$structure->relations = [
			'Item' => [
				'entity' => Item::class,
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true,
			],
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}
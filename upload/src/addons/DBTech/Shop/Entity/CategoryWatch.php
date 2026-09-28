<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property int $category_id
 * @property string $notify_on
 * @property bool $send_alert
 * @property bool $send_email
 * @property bool $include_children
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Category $Category
 * @property \XF\Entity\User $User
 */
class CategoryWatch extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_category_watch';
		$structure->shortName = 'DBTech\Shop:CategoryWatch';
		$structure->primaryKey = ['user_id', 'category_id'];
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'category_id' => ['type' => self::UINT, 'required' => true],
			'notify_on' => ['type' => self::STR, 'default' => '',
				'allowedValues' => ['', 'item']
			],
			'send_alert' => ['type' => self::BOOL, 'default' => false],
			'send_email' => ['type' => self::BOOL, 'default' => false],
			'include_children' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->getters = [];
		$structure->relations = [
			'Category' => [
				'entity' => 'DBTech\Shop:Category',
				'type' => self::TO_ONE,
				'conditions' => 'category_id',
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
<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $thread_id
 * @property int $user_id
 *
 * RELATIONS
 * @property \XF\Entity\Thread $Thread
 * @property \XF\Entity\User $User
 */
class ThreadBan extends Entity
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_thread_ban';
		$structure->shortName = 'DBTech\Shop:ThreadBan';
		$structure->primaryKey = ['thread_id', 'user_id'];
		$structure->columns = [
			'thread_id' 	=> ['type' => self::UINT, 'required' => true],
			'user_id' 		=> ['type' => self::UINT, 'required' => true],
		];
		$structure->relations = [
			'Thread' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => 'thread_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		return $structure;
	}
}
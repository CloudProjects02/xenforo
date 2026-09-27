<?php

namespace DBTech\Shop\Entity;

use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $thread_id
 * @property int $user_id
 *
 * RELATIONS
 * @property-read Thread|null $Thread
 * @property-read User|null $User
 */
class ThreadBan extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
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
				'entity' => Thread::class,
				'type' => self::TO_ONE,
				'conditions' => 'thread_id',
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
<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property bool $is_staff
 * @property string $action
 * @property string $hash
 *
 * RELATIONS
 * @property-read User|null $User
 */
class AccountLock extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_account_lock';
		$structure->shortName = 'DBTech\Security:AccountLock';
		$structure->primaryKey = 'user_id';
		$structure->columns = [
			'user_id'	=> ['type' => self::UINT, 'required' => true],
			'is_staff' 	=> ['type' => self::BOOL, 'default' => false],
			'action' 	=> ['type' => self::STR, 'required' => true],
			'hash' 		=> ['type' => self::STR, 'required' => true],
		];
		$structure->relations = [
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
<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $admin_strike_id
 * @property int $user_id
 * @property string $ipaddress
 * @property int $dateline
 * @property string $username
 *
 * RELATIONS
 * @property-read User|null $User
 */
class AdminStrike extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_admin_strike';
		$structure->shortName = 'DBTech\Security:AdminStrike';
		$structure->primaryKey = 'admin_strike_id';
		$structure->columns = [
			'admin_strike_id'	=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' 			=> ['type' => self::UINT, 'required' => true],
			'ipaddress' 		=> ['type' => self::STR, 'required' => true],
			'dateline' 			=> ['type' => self::UINT, 'default' => \XF::$time],
			'username' 			=> ['type' => self::STR],
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
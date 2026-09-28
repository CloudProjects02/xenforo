<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property string $ipaddress
 * @property int $first_visit
 * @property int $last_visit
 *
 * RELATIONS
 * @property-read User|null $User
 */
class IpLog extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_ip_log';
		$structure->shortName = 'DBTech\Security:IpLog';
		$structure->primaryKey = ['user_id', 'ipaddress'];
		$structure->columns = [
			'user_id' 			=> ['type' => self::UINT, 'required' => true],
			'ipaddress' 		=> ['type' => self::STR, 'required' => true],
			'first_visit' 		=> ['type' => self::UINT, 'default' => \XF::$time],
			'last_visit' 		=> ['type' => self::UINT, 'default' => \XF::$time],
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
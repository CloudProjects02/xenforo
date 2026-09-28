<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $ip_verify_id
 * @property int $user_id
 * @property string $ipaddress
 * @property int $dateline
 *
 * RELATIONS
 * @property-read User|null $User
 */
class IpVerify extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_ip_verify';
		$structure->shortName = 'DBTech\Security:IpVerify';
		$structure->primaryKey = 'ip_verify_id';
		$structure->columns = [
			'ip_verify_id'		=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' 			=> ['type' => self::UINT, 'required' => true],
			'ipaddress' 		=> ['type' => self::STR, 'required' => true],
			'dateline' 			=> ['type' => self::UINT, 'default' => \XF::$time],
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
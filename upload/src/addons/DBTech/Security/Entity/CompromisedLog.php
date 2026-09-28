<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $compromised_log_id
 * @property int $user_id
 * @property string $ipaddress
 * @property int $dateline
 * @property string $attempted_usernames
 *
 * RELATIONS
 * @property-read User|null $User
 */
class CompromisedLog extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_compromised_log';
		$structure->shortName = 'DBTech\Security:CompromisedLog';
		$structure->primaryKey = 'compromised_log_id';
		$structure->columns = [
			'compromised_log_id' 	=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' 				=> ['type' => self::UINT, 'required' => true],
			'ipaddress' 			=> ['type' => self::STR, 'required' => true],
			'dateline' 				=> ['type' => self::UINT, 'default' => \XF::$time],
			'attempted_usernames' 	=> ['type' => self::STR, 'default' => ''],
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
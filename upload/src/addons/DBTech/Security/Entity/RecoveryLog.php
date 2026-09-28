<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $recovery_log_id
 * @property int $user_id
 * @property string $ipaddress
 * @property int $dateline
 * @property string $username
 * @property string $existing_email
 * @property string $new_email
 * @property string $pm_user
 * @property int $score
 * @property array $breakdown
 *
 * RELATIONS
 * @property-read User|null $User
 */
class RecoveryLog extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_recovery_log';
		$structure->shortName = 'DBTech\Security:RecoveryLog';
		$structure->primaryKey = 'recovery_log_id';
		$structure->columns = [
			'recovery_log_id'	=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' 			=> ['type' => self::UINT, 'required' => true],
			'ipaddress' 		=> ['type' => self::STR, 'required' => true],
			'dateline' 			=> ['type' => self::UINT, 'default' => \XF::$time],
			'username' 			=> ['type' => self::STR, 'required' => true],
			'existing_email' 	=> ['type' => self::STR, 'required' => true],
			'new_email' 		=> ['type' => self::STR, 'required' => true],
			'pm_user' 			=> ['type' => self::STR],
			'score' 			=> ['type' => self::UINT],
			'breakdown' 		=> ['type' => self::JSON_ARRAY],
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
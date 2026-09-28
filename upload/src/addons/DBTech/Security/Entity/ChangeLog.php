<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $change_log_id
 * @property int $user_id
 * @property string $ipaddress
 * @property int $dateline
 * @property string $script
 * @property string $action
 * @property int $id
 * @property string $title
 * @property string $field
 * @property string $change
 *
 * RELATIONS
 * @property-read User|null $User
 */
class ChangeLog extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_change_log';
		$structure->shortName = 'DBTech\Security:ChangeLog';
		$structure->primaryKey = 'change_log_id';
		$structure->columns = [
			'change_log_id'		=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' 			=> ['type' => self::UINT, 'required' => true],
			'ipaddress' 		=> ['type' => self::STR, 'required' => true],
			'dateline' 			=> ['type' => self::UINT, 'default' => \XF::$time],
			'script' 			=> ['type' => self::STR, 'required' => true],
			'action' 			=> ['type' => self::STR, 'required' => true],
			'id' 				=> ['type' => self::UINT, 'required' => true],
			'title' 			=> ['type' => self::STR, 'required' => true],
			'field' 			=> ['type' => self::STR, 'required' => true],
			'change' 			=> ['type' => self::STR, 'default' => ''],
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
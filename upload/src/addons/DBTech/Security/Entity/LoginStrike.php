<?php

namespace DBTech\Security\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $login_strike_id
 * @property int $dateline
 * @property string $ipaddress
 * @property string $username
 * @property bool $valid_user
 */
class LoginStrike extends Entity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_login_strike';
		$structure->shortName = 'DBTech\Security:LoginStrike';
		$structure->primaryKey = 'login_strike_id';
		$structure->columns = [
			'login_strike_id'	=> ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'dateline' 			=> ['type' => self::UINT, 'default' => \XF::$time],
			'ipaddress' 		=> ['type' => self::STR, 'required' => true],
			'username' 			=> ['type' => self::STR, 'required' => true],
			'valid_user' 		=> ['type' => self::BOOL, 'default' => true],
		];

		return $structure;
	}
}
<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $fingerprint_log_id
 * @property string $fingerprint
 * @property int $user_id
 * @property string $ipaddress
 * @property int $dateline
 * @property array $components
 *
 * RELATIONS
 * @property-read User|null $User
 */
class FingerprintLog extends Entity
{
	/**
	 * @return bool
	 */
	protected function _preSave(): bool
	{
		$match = \XF::app()->em()->getFinder($this->_structure->shortName)
			->where('fingerprint', '=', $this->fingerprint)
			->where('user_id', '=', $this->user_id)
			->fetchOne()
		;
		if ($match && $match !== $this)
		{
			$this->error(\XF::phrase('all_x_values_must_be_unique', ['key' => 'fingerprint']), 'fingerprint');
			return false;
		}

		return true;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_fingerprint_log';
		$structure->shortName = 'DBTech\Security:FingerprintLog';
		$structure->primaryKey = 'fingerprint_log_id';
		$structure->columns = [
			'fingerprint_log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'fingerprint' => ['type' => self::STR, 'unique' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'ipaddress' => ['type' => self::STR, 'required' => true],
			'dateline' => ['type' => self::UINT, 'default' => \XF::$time],
			'components' => ['type' => self::JSON_ARRAY, 'required' => true],
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
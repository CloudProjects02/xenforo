<?php

namespace DBTech\Security\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $watcher_log_id
 * @property int $log_date
 * @property int $user_id
 * @property string $ip_address
 * @property int $watcher_id
 * @property string $action
 * @property array $action_params
 * @property string $message
 *
 * RELATIONS
 * @property-read Watcher|null $Watcher
 * @property-read User|null $User
 */
class WatcherLog extends Entity
{
	/**
	 * @return Phrase
	 */
	public function getActionPhrase(): Phrase
	{
		return \XF::phrase('dbtech_security_watcher_action.' . $this->action);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_watcher_log';
		$structure->shortName = 'DBTech\Security:WatcherLog';
		$structure->primaryKey = 'watcher_log_id';
		$structure->columns = [
			'watcher_log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'log_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'user_id' => ['type' => self::UINT, 'default' => 0],
			'ip_address' => ['type' => self::STR, 'maxLength' => 43, 'default' => ''],
			'watcher_id' => ['type' => self::UINT, 'required' => true],
			'action' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'action_params' => ['type' => self::JSON_ARRAY, 'default' => ''],
			'message' => ['type' => self::STR, 'default' => ''],
		];
		$structure->relations = [
			'Watcher' => [
				'entity' => Watcher::class,
				'type' => self::TO_ONE,
				'conditions' => 'watcher_id',
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
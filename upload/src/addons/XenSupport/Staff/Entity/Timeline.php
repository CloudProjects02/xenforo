<?php

namespace XenSupport\Staff\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int    $event_id
 * @property int    $user_id
 * @property string $event_type
 * @property string $event_title
 * @property string $event_note
 * @property int    $event_date
 * @property string $icon
 * @property int    $created_date
 * @property bool   $is_visible
 *
 * @property \XF\Entity\User|null $User
 */
class Timeline extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_xs_staff_timeline';
		$structure->shortName = 'XenSupport\Staff:Timeline';
		$structure->primaryKey = 'event_id';
		$structure->columns = [
			'event_id'     => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id'      => ['type' => self::UINT, 'required' => true],
			'event_type'   => ['type' => self::STR, 'default' => 'custom',
				'allowedValues' => ['joined', 'promoted', 'paused', 'returned', 'milestone', 'custom']],
			'event_title'  => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'event_note'   => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
			'event_date'   => ['type' => self::UINT, 'required' => true],
			'icon'         => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'created_date' => ['type' => self::UINT, 'default' => 0],
			'is_visible'   => ['type' => self::BOOL, 'default' => true],
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];
		return $structure;
	}

	protected function _preSave(): void
	{
		if (!$this->created_date) { $this->created_date = \XF::$time; }
	}
}

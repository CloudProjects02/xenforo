<?php

namespace XenSupport\Staff\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $thank_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property int $thank_date
 *
 * RELATIONS
 * @property \XF\Entity\User $FromUser
 * @property \XF\Entity\User $ToUser
 */
class Thank extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_xs_staff_thank';
		$structure->shortName = 'XenSupport\Staff:Thank';
		$structure->primaryKey = 'thank_id';
		$structure->columns = [
			'thank_id'     => ['type' => self::UINT, 'autoIncrement' => true],
			'from_user_id' => ['type' => self::UINT, 'required' => true],
			'to_user_id'   => ['type' => self::UINT, 'required' => true],
			'thank_date'   => ['type' => self::UINT, 'default' => \XF::$time],
		];
		$structure->relations = [
			'FromUser' => ['entity' => 'XF:User', 'type' => self::TO_ONE, 'conditions' => [['user_id', '=', '$from_user_id']], 'primary' => true],
			'ToUser'   => ['entity' => 'XF:User', 'type' => self::TO_ONE, 'conditions' => [['user_id', '=', '$to_user_id']],   'primary' => true],
		];
		return $structure;
	}
}

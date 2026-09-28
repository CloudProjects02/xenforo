<?php

namespace XenSupport\Staff\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);
		$structure->relations['XSStaffProfile'] = [
			'entity' => 'XenSupport\Staff:Profile',
			'type' => self::TO_ONE,
			'conditions' => 'user_id',
			'primary' => true,
		];
		$structure->relations['XSStaffUserGroup'] = [
			'entity' => 'XF:UserGroup',
			'type' => self::TO_ONE,
			'conditions' => [['user_group_id', '=', '$user_group_id']],
			'primary' => true,
		];
		return $structure;
	}
}

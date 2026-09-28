<?php

namespace EAEAddons\ThreadCount\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['eaetc_thread_count'] = ['type' => self::UINT, 'default' => 0, 'forced' => true, 'changeLog' => false];
		return $structure;
	}
}
<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\UserOption
 */
class UserOption extends XFCP_UserOption
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['dbtech_security_is_user_locked'] = ['type' => Entity::BOOL, 'default' => false];
		$structure->columns['dbtech_security_is_admin_locked'] = ['type' => Entity::BOOL, 'default' => false];

		return $structure;
	}
}
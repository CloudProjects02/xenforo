<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\UserTfaTrusted
 */
class UserTfaTrusted extends XFCP_UserTfaTrusted
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

		$structure->columns['dbtech_security_user_agent'] = ['type' => Entity::STR];

		return $structure;
	}
}
<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\UserRemember
 */
class UserRemember extends XFCP_UserRemember
{
	protected function _preSave()
	{
		if (!$this->dbtech_security_user_agent)
		{
			$this->dbtech_security_user_agent = \XF::app()->request()->getUserAgent();
		}
	}

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
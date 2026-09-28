<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\IpMatch
 */
class IpMatch extends XFCP_IpMatch
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

		$structure->columns['match_type']['allowedValues'][] = 'dbtech_security_tor';
		$structure->columns['match_type']['allowedValues'][] = 'dbtech_security_country';

		$structure->columns['dbtech_security_comment'] = ['type' => Entity::STR, 'default' => ''];

		return $structure;
	}
}
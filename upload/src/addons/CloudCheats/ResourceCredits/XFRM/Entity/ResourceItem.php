<?php

namespace CloudCheats\ResourceCredits\XFRM\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @extends \XFRM\Entity\ResourceItem
 */
class ResourceItem extends XFCP_ResourceItem
{
	public static function getStructure(Structure $structure): Structure
	{
		$structure = parent::getStructure($structure);

		$structure->columns['cc_credits_price'] = [
			'type'     => self::FLOAT,
			'nullable' => true,
			'default'  => null,
		];

		return $structure;
	}
}

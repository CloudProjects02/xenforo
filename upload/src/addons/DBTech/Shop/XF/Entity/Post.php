<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\Post
 */
class Post extends XFCP_Post
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$visitor = \XF::visitor();
		if ($visitor->user_id)
		{
			$structure->relations['Thread']['with'][] = 'DBTechShopThreadBans|' . $visitor->user_id;
		}

		return $structure;
	}
}
<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Entity;

use XF\Mvc\Entity\Structure;

class Post extends XFCP_Post
{
	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
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
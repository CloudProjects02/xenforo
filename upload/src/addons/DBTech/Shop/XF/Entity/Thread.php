<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * RELATIONS
 * @property \DBTech\Shop\Entity\ThreadBan[] DBTechShopThreadBans
 */
class Thread extends XFCP_Thread
{
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canView(&$error = null)
	{
		$visitor = \XF::visitor();
		
		$previous = parent::canView($error);
		if ($previous && $visitor->user_id && $this->DBTechShopThreadBans[$visitor->user_id])
		{
			return false;
		}

		return $previous;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);
		
		$structure->relations['DBTechShopThreadBans'] = [
			'entity' => 'DBTech\Shop:ThreadBan',
			'type' => self::TO_MANY,
			'conditions' => 'thread_id',
			'key' => 'user_id'
		];
		
		$visitor = \XF::visitor();
		if ($visitor->user_id)
		{
			$structure->defaultWith[] = 'DBTechShopThreadBans|' . $visitor->user_id;
		}
		
		return $structure;
	}
}
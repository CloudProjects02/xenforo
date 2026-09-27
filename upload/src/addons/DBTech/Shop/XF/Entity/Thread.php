<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Entity;

use DBTech\Shop\Entity\ThreadBan;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\Thread
 *
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
	 * @param Structure $structure
	 *
	 * @return Structure
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->relations['DBTechShopThreadBans'] = [
			'entity' => ThreadBan::class,
			'type' => self::TO_MANY,
			'conditions' => 'thread_id',
			'key' => 'user_id',
		];

		$structure->withAliases['full'][] = function ()
		{
			$userId = \XF::visitor()->user_id;
			if ($userId)
			{
				return [
					'DBTechShopThreadBans|' . $userId,
				];
			}

			return null;
		};

		$visitor = \XF::visitor();
		if ($visitor->user_id)
		{
			$structure->defaultWith[] = 'DBTechShopThreadBans|' . $visitor->user_id;
		}

		return $structure;
	}
}
<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\TagContent
 */
class TagContent extends XFCP_TagContent
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

		if (empty($structure->relations['AddUser']))
		{
			$structure->relations['AddUser'] = [
				'entity'     => User::class,
				'type'       => self::TO_ONE,
				'conditions' => [['user_id', '=', '$add_user_id']],
				'primary'    => true,
			];
		}
		else if ($structure->relations['AddUser']['conditions'] == 'user_id')
		{
			$structure->relations['AddUser']['conditions'] = [['user_id', '=', '$add_user_id']];
		}

		return $structure;
	}
}
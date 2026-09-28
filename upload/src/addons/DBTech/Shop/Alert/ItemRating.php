<?php

namespace DBTech\Shop\Alert;

use XF\Alert\AbstractHandler;

/**
 * Class ItemRating
 *
 * @package DBTech\Shop\Alert
 */
class ItemRating extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Item', 'Item.Permissions|' . $visitor->permission_combination_id, 'Item.Category', 'Item.Category.Permissions|' . $visitor->permission_combination_id];
	}
	
	/**
	 * @return array
	 */
	public function getOptOutActions(): array
	{
		return [
			'review',
		];
	}
	
	/**
	 * @return int
	 */
	public function getOptOutDisplayOrder(): int
	{
		return 89997;
	}
}
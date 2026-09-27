<?php

namespace DBTech\Shop\Alert;

use DBTech\Shop\Entity\Item;
use XF\Alert\AbstractHandler;
use XF\Entity\UserAlert;

class ItemHandler extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Permissions|' . $visitor->permission_combination_id, 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}

	/**
	 * @param UserAlert $alert
	 * @param $error
	 *
	 * @return bool
	 */
	public function canViewAlert(UserAlert $alert, &$error = null): bool
	{
		/** @var Item $item */
		$item = $alert->getContent();

		return !$item->is_stealth_item;
	}

	/**
	 * @return array
	 */
	public function getOptOutActions(): array
	{
		return [
			'insert',
			'mention',
			'reaction',
			'gift',
		];
	}

	/**
	 * @return int
	 */
	public function getOptOutDisplayOrder(): int
	{
		return 89995;
	}
}
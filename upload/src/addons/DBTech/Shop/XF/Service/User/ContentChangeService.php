<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Service\User;

use DBTech\Shop\Repository\ItemRepository;
use DBTech\Shop\Repository\PurchaseRepository;

/**
 * @extends \XF\Service\User\ContentChangeService
 */
class ContentChangeService extends XFCP_ContentChangeService
{
	protected function stepRebuildFinalCaches()
	{
		parent::stepRebuildFinalCaches();

		if ($this->newUserId === null)
		{
			return;
		}

		$repo = \XF::app()->repository(PurchaseRepository::class);
		$count = $repo->getPurchaseCount($this->newUserId);

		\XF::app()->db()->update('xf_user', ['dbtech_shop_purchases' => $count], 'user_id = ?', $this->newUserId);


		$repo = \XF::app()->repository(ItemRepository::class);
		$count = $repo->getUserItemCount($this->newUserId);

		\XF::app()->db()->update('xf_user', ['dbtech_shop_item_count' => $count], 'user_id = ?', $this->newUserId);
	}
}
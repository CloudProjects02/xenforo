<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Service\User;

/**
 * Class ContentChange
 * @package DBTech\Shop\XF\Service\User
 */
class ContentChange extends XFCP_ContentChange
{
	protected function stepRebuildFinalCaches()
	{
		parent::stepRebuildFinalCaches();
		
		if ($this->newUserId === null)
		{
			return;
		}
		
		/** @var \DBTech\Shop\Repository\Purchase $repo */
		$repo = $this->app->repository('DBTech\Shop:Purchase');
		$count = $repo->getPurchaseCount($this->newUserId);
		
		$this->app->db()->update('xf_user', ['dbtech_shop_purchases' => $count], 'user_id = ?', $this->newUserId);
		
		
		/** @var \DBTech\Shop\Repository\Item $repo */
		$repo = $this->app->repository('DBTech\Shop:Item');
		$count = $repo->getUserItemCount($this->newUserId);
		
		$this->app->db()->update('xf_user', ['dbtech_shop_item_count' => $count], 'user_id = ?', $this->newUserId);
	}
}